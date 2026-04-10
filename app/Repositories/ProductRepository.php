<?php

namespace App\Repositories;

use App\DTO\Product\ProductDTO;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Models\Product;
use App\Services\BarcodeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductRepository
{
    public function __construct(
        protected Product $model,
        private BarcodeService $barcodeService,
    ) {}

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->with(['variants.images', 'images']);

        if (request()->has('highlighted')) {
            $query->where('is_highlighted', filter_var(request('highlighted'), FILTER_VALIDATE_BOOLEAN));
        }

        if (request()->has('promotion')) {
            $query->where('is_promotion', filter_var(request('promotion'), FILTER_VALIDATE_BOOLEAN));
        }

        if (request()->has('active')) {
            $query->where('active', filter_var(request('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage);
    }

    public function getAll()
    {
        return $this->model
            ->with(['variants.images', 'images'])
            ->where('active', true)
            ->get();
    }    

    public function store(StoreProductRequest $request): Product
    {
        return DB::transaction(function () use ($request) {
            $baseSlug = Str::slug($request->name);
            $slug = $baseSlug;
            $counter = 1;
            while ($this->model->withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $attributes = [
                'name' => $request->name,
                'reference' => $request->reference,
                'description' => $request->description,
                'retail_price' => $request->retail_price,
                'wholesale_price' => $request->wholesale_price,
                'wholesale_min_qty' => $request->wholesale_min_qty,
                'category_id' => $request->category_id,
                'is_highlighted' => filter_var($request->is_highlighted, FILTER_VALIDATE_BOOLEAN),
                'is_promotion' => filter_var($request->is_promotion, FILTER_VALIDATE_BOOLEAN),
                'promotion_price' => $request->promotion_price,
                'ml_price' => $request->ml_price,
                'promotion_percent' => null,
                'is_new' => filter_var($request->is_new, FILTER_VALIDATE_BOOLEAN),
                'is_new_collection' => filter_var($request->is_new_collection, FILTER_VALIDATE_BOOLEAN),
                'active' => filter_var($request->active, FILTER_VALIDATE_BOOLEAN),
                'slug' => $slug,
                'weight' => $request->weight,
                'width' => $request->width,
                'height' => $request->height,
                'length' => $request->length,
            ];

            if ($attributes['is_promotion'] && $attributes['promotion_price'] && $attributes['retail_price'] > 0) {
                $attributes['promotion_percent'] = round(
                    100 - (($attributes['promotion_price'] / $attributes['retail_price']) * 100),
                    2
                );
            } else {
                $attributes['promotion_price'] = null;
                $attributes['promotion_percent'] = null;
            }

            $product = $this->model->create($attributes);

            // Criação das variantes com SKU automático se não informado
            $variants = $request->input('variants', []);
            $variantsToCreate = [];

            foreach ($variants as $variantData) {
                // Gera SKU automaticamente se não informado
                if (empty($variantData['sku'])) {
                    $slugBase = Str::slug($product->name);
                    $colorSlug = Str::slug($variantData['color'] ?? '');
                    $sizeSlug = Str::slug($variantData['size'] ?? '');
                    $variantData['sku'] = strtoupper("{$slugBase}-{$colorSlug}-{$sizeSlug}");
                }
                $variantsToCreate[] = $variantData;
            }

            $product->variants()->createMany($variantsToCreate);
            $product->load('variants');
            $variantMap = $product->variants->pluck('id', 'sku');

            // Gera código de barras EAN-13 para cada variante
            foreach ($product->variants as $variant) {
                $variant->barcode = $this->barcodeService->generateUniqueBarcode();
                $variant->save();
            }

            // Cria movimentos de estoque inicial para variantes com estoque > 0
            $initialMovements = [];
            foreach ($product->variants as $variant) {
                if ($variant->stock > 0) {
                    $initialMovements[] = [
                        'id'                 => (string) Str::uuid(),
                        'product_variant_id' => $variant->id,
                        'type'               => 'in',
                        'reason'             => 'manual_add',
                        'quantity'           => $variant->stock,
                        'stock_before'       => 0,
                        'stock_after'        => $variant->stock,
                        'notes'              => 'Estoque inicial no cadastro do produto',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }
            }
            if (!empty($initialMovements)) {
                \App\Models\InventoryMovement::insert($initialMovements);
            }

            // Processamento das imagens
            $newImagesToCreate = [];

            foreach ($request->input('images', []) as $index => $imageInput) {
                $file = $request->file("images.{$index}.image");
                if ($file && $file instanceof \Illuminate\Http\UploadedFile) {
                    $path = $file->store('products', 'public');
                    $variantSku = $imageInput['variant_sku'] ?? null;
                    $isMain = filter_var($imageInput['is_main'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    $newImagesToCreate[] = [
                        'id' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'variant_id' => $variantSku ? ($variantMap[$variantSku] ?? null) : null,
                        'url' => Storage::url($path),
                        'is_main' => $isMain,
                        'position' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($newImagesToCreate)) {
                $product->images()->insert($newImagesToCreate);
            }

            return $product->load('variants', 'images', 'kits.items.variant');
        });
    }

    public function storeFromDTO(ProductDTO $data): Product
    {
        return DB::transaction(function () use ($data) {
            $baseSlug = Str::slug($data->name);
            $slug = $baseSlug;
            $counter = 1;
            while ($this->model->withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $attributes = [
                'name' => $data->name,
                'reference' => $data->reference,
                'description' => $data->description,
                'retail_price' => $data->retail_price,
                'wholesale_price' => $data->wholesale_price,
                'wholesale_min_qty' => $data->wholesale_min_qty,
                'category_id' => $data->category_id,
                'is_highlighted' => $data->is_highlighted,
                'is_promotion' => $data->is_promotion,
                'promotion_price' => $data->promotion_price,
                'ml_price' => $data->ml_price ?? null,
                'promotion_percent' => null,
                'is_new' => $data->is_new,
                'is_new_collection' => $data->is_new_collection,
                'active' => $data->active,
                'slug' => $slug,
                'weight' => $data->weight,
                'width' => $data->width,
                'height' => $data->height,
                'length' => $data->length,
            ];

            if ($data->is_promotion && $data->promotion_price && $data->retail_price > 0) {
                $attributes['promotion_percent'] = round(
                    100 - (($data->promotion_price / $data->retail_price) * 100),
                    2
                );
            }

            $product = $this->model->create($attributes);

            // Variantes com SKU automático se não informado
            $variantsToCreate = [];
            foreach ($data->variants as $variantData) {
                if (empty($variantData['sku'])) {
                    $slugBase = Str::slug($product->name);
                    $colorSlug = Str::slug($variantData['color'] ?? '');
                    $sizeSlug = Str::slug($variantData['size'] ?? '');
                    $variantData['sku'] = strtoupper("{$slugBase}-{$colorSlug}-{$sizeSlug}");
                }
                $variantsToCreate[] = $variantData;
            }

            $product->variants()->createMany($variantsToCreate);
            $product->load('variants');
            $variantMap = $product->variants->pluck('id', 'sku');

            // Gera código de barras EAN-13 para cada variante
            foreach ($product->variants as $variant) {
                $variant->barcode = $this->barcodeService->generateUniqueBarcode();
                $variant->save();
            }

            // Cria movimentos de estoque inicial para variantes com estoque > 0
            $initialMovements = [];
            foreach ($product->variants as $variant) {
                if ($variant->stock > 0) {
                    $initialMovements[] = [
                        'id'                 => (string) Str::uuid(),
                        'product_variant_id' => $variant->id,
                        'type'               => 'in',
                        'reason'             => 'manual_add',
                        'quantity'           => $variant->stock,
                        'stock_before'       => 0,
                        'stock_after'        => $variant->stock,
                        'notes'              => 'Estoque inicial no cadastro do produto',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }
            }
            if (!empty($initialMovements)) {
                \App\Models\InventoryMovement::insert($initialMovements);
            }

            // Processamento das imagens
            $currentImageIds = $product->images->pluck("id")->toArray();
            $submittedImageIds = [];
            $newImagesToCreate = [];
            $positionCounter = 0;

            foreach ($data->images as $imageInput) {
                $imageId = $imageInput["id"] ?? null;
                $isMain = filter_var($imageInput["is_main"] ?? false, FILTER_VALIDATE_BOOLEAN);
                $variantSku = $imageInput["variant_sku"] ?? null;
                $file = $imageInput["image"] ?? null; // This will be the UploadedFile object from the DTO

                if ($imageId && in_array($imageId, $currentImageIds)) {
                    // Imagem existente: atualizar metadados
                    $productImage = $product->images->where("id", $imageId)->first();
                    if ($productImage) {
                        $productImage->update([
                            "is_main" => $isMain,
                            "variant_id" => $variantSku ? ($variantMap[$variantSku] ?? null) : null,
                            "position" => $positionCounter,
                        ]);
                    }
                    $submittedImageIds[] = $imageId;
                } else if ($file && $file instanceof \Illuminate\Http\UploadedFile) {
                    // Nova imagem: fazer upload e criar
                    $path = $file->store("products", "public");
                    $newImagesToCreate[] = [
                        "id" => (string) Str::uuid(),
                        "product_id" => $product->id,
                        "variant_id" => $variantSku ? ($variantMap[$variantSku] ?? null) : null,
                        "url" => Storage::url($path),
                        "is_main" => $isMain,
                        "position" => $positionCounter,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ];
                } else if ($imageInput["url"] ?? null) {
                    // Se for uma imagem existente que não teve um novo arquivo selecionado, mas a URL foi enviada
                    // Adicionamos o ID da imagem existente para que ela não seja excluída
                    $existingImage = $product->images->where("url", $imageInput["url"])->first();
                    if ($existingImage) {
                        $existingImage->update(["position" => $positionCounter]);
                        $submittedImageIds[] = $existingImage->id;
                    }
                }

                $positionCounter++;
            }

            // Excluir imagens que não foram submetidas no request
            $imagesToDelete = array_diff($currentImageIds, $submittedImageIds);
            if (!empty($imagesToDelete)) {
                $product->images()->whereIn("id", $imagesToDelete)->delete();
                // Opcional: deletar arquivos físicos do storage
                foreach ($product->images()->whereIn("id", $imagesToDelete)->get() as $deletedImage) {
                    Storage::disk("public")->delete(str_replace("/storage/", "", $deletedImage->url));
                }
            }

            // Inserir novas imagens
            if (!empty($newImagesToCreate)) {
                $product->images()->insert($newImagesToCreate);
            }

            return $product->load('variants', 'images', 'kits.items.variant');
        });
    }

    
    public function show(string $id): Product
    {
        return $this->model->with(['variants.images', 'images', 'category', 'kits'])->findOrFail($id);
    }

    public function showBySlug(string $slug): Product
    {
        return $this->model
            ->with([
                'category',
                'images.variant',
                'variants',
                'kits.items.variant',
                'measurements',
            ])
            ->where('slug', $slug)
            ->where('active', true)
            ->firstOrFail();
    }

public function update(Product $product, ProductDTO $data): Product
{
    return DB::transaction(function () use ($product, $data) {
        // ====== Atualiza atributos principais (apenas os enviados) ======
        $attributes = [];

        // Atualiza apenas os campos que foram enviados
        if ($data->name !== null) {
            $attributes['name'] = $data->name;

            // Gerar slug único apenas se o nome mudou
            $baseSlug = Str::slug($data->name);
            $slug = $baseSlug;
            $counter = 1;
            while ($this->model->withTrashed()->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $attributes['slug'] = $slug;
        }

        if ($data->reference !== null) $attributes['reference'] = $data->reference;
        if ($data->description !== null) $attributes['description'] = $data->description;
        if ($data->retail_price !== null) $attributes['retail_price'] = $data->retail_price;
        if ($data->wholesale_price !== null) $attributes['wholesale_price'] = $data->wholesale_price;
        if ($data->wholesale_min_qty !== null) $attributes['wholesale_min_qty'] = $data->wholesale_min_qty;
        if ($data->category_id !== null) $attributes['category_id'] = $data->category_id;
        if ($data->is_highlighted !== null) $attributes['is_highlighted'] = $data->is_highlighted;
        if ($data->is_promotion !== null) $attributes['is_promotion'] = $data->is_promotion;
        if ($data->promotion_price !== null) $attributes['promotion_price'] = $data->promotion_price;
        $attributes['ml_price'] = $data->ml_price; // null limpa o campo; valor define o preço ML
        if ($data->is_new !== null) $attributes['is_new'] = $data->is_new;
        if ($data->is_new_collection !== null) $attributes['is_new_collection'] = $data->is_new_collection;
        if ($data->active !== null) $attributes['active'] = $data->active;
        if ($data->weight !== null) $attributes['weight'] = $data->weight;
        if ($data->width !== null) $attributes['width'] = $data->width;
        if ($data->height !== null) $attributes['height'] = $data->height;
        if ($data->length !== null) $attributes['length'] = $data->length;

        // Calcula promotion_percent se necessário
        $isPromotion = $data->is_promotion ?? $product->is_promotion;
        $promotionPrice = $data->promotion_price ?? $product->promotion_price;
        $retailPrice = $data->retail_price ?? $product->retail_price;

        if ($isPromotion && $promotionPrice && $retailPrice > 0) {
            $attributes['promotion_percent'] = round(
                100 - (($promotionPrice / $retailPrice) * 100),
                2
            );
        } elseif ($data->is_promotion === false) {
            $attributes['promotion_price'] = null;
            $attributes['promotion_percent'] = null;
        }

        $product->update($attributes);

        // ====== Atualiza variantes (apenas se foram enviadas) ======
        if ($data->variants !== null) {
            // Separa ativas e soft-deletadas para evitar ambiguidade ao fazer keyBy('sku')
            $activeVariants  = $product->variants()->get()->keyBy('sku');
            $trashedVariants = $product->variants()->onlyTrashed()->get()->keyBy('sku');
            $submittedSkus   = [];

            foreach ($data->variants as $variantData) {
                // Gera SKU automático se não informado
                if (empty($variantData['sku'])) {
                    $slugBase  = Str::slug($product->name);
                    $colorSlug = Str::slug($variantData['color'] ?? '');
                    $sizeSlug  = Str::slug($variantData['size'] ?? '');
                    $variantData['sku'] = strtoupper("{$slugBase}-{$colorSlug}-{$sizeSlug}");
                }

                $sku = $variantData['sku'];
                $submittedSkus[] = $sku;

                if ($activeVariants->has($sku)) {
                    // Atualiza variante ativa — preserva ID e histórico de movimentos
                    $activeVariants[$sku]->update([
                        'size'  => $variantData['size']  ?? $activeVariants[$sku]->size,
                        'color' => $variantData['color'] ?? $activeVariants[$sku]->color,
                        'stock' => $variantData['stock'] ?? $activeVariants[$sku]->stock,
                    ]);
                } elseif ($trashedVariants->has($sku)) {
                    // Restaura variante soft-deletada (observer restaura os movimentos)
                    $existing = $trashedVariants[$sku];
                    $existing->restore();
                    $existing->update([
                        'size'  => $variantData['size']  ?? $existing->size,
                        'color' => $variantData['color'] ?? $existing->color,
                        'stock' => $variantData['stock'] ?? $existing->stock,
                    ]);
                    $activeVariants[$sku] = $existing;
                } else {
                    // Cria nova variante
                    $newVariant = $product->variants()->create([
                        'sku'     => $sku,
                        'size'    => $variantData['size']  ?? null,
                        'color'   => $variantData['color'] ?? null,
                        'stock'   => $variantData['stock'] ?? 0,
                        'barcode' => $this->barcodeService->generateUniqueBarcode(),
                    ]);
                    $activeVariants[$sku] = $newVariant;
                }
            }

            // Remove variantes ativas que saíram da lista via soft delete (preserva histórico)
            $skusToRemove = $activeVariants->keys()->diff($submittedSkus);
            foreach ($skusToRemove as $sku) {
                $activeVariants[$sku]->delete(); // soft delete → observer soft-deleta os movimentos
            }

            // Garante barcode em todas as variantes existentes que não tenham
            $product->load('variants');
            foreach ($product->variants as $variant) {
                if (empty($variant->barcode)) {
                    $variant->update(['barcode' => $this->barcodeService->generateUniqueBarcode()]);
                }
            }
        }

        $variantMap = collect($product->variants)->pluck('id', 'sku');

        // ====== Atualiza imagens (apenas se foram enviadas) ======
        if ($data->images !== null) {
            $currentImages = $product->images()->get();
            $currentImageIds = $currentImages->pluck("id")->toArray();

            $submittedImageIds = [];
            $newImagesToCreate = [];
            $positionCounter = 0;

            foreach ($data->images as $imageInput) {
                $imageId = $imageInput["id"] ?? null;
                $isMain = filter_var($imageInput["is_main"] ?? false, FILTER_VALIDATE_BOOLEAN);
                $variantSku = $imageInput["variant_sku"] ?? null;
                $file = $imageInput["image"] ?? null;

                if ($imageId && in_array($imageId, $currentImageIds)) {
                    //  Atualiza imagem existente (metadados)
                    $productImage = $currentImages->firstWhere("id", $imageId);
                    if ($productImage) {
                        $productImage->update([
                            "is_main" => $isMain,
                            "variant_id" => $variantSku ? ($variantMap[$variantSku] ?? null) : null,
                            "position" => $positionCounter,
                        ]);
                    }
                    $submittedImageIds[] = $imageId;
                } elseif ($file && $file instanceof \Illuminate\Http\UploadedFile) {
                    //  Nova imagem (upload)
                    $path = $file->store("products", "public");
                    $newImagesToCreate[] = [
                        "id" => (string) Str::uuid(),
                        "product_id" => $product->id,
                        "variant_id" => $variantSku ? ($variantMap[$variantSku] ?? null) : null,
                        "url" => Storage::url($path),
                        "is_main" => $isMain,
                        "position" => $positionCounter,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ];
                } elseif (!empty($imageInput["url"])) {
                    //  Imagem existente apenas por URL (mantém)
                    $existing = $currentImages->firstWhere("url", $imageInput["url"]);
                    if ($existing) {
                        $existing->update(["position" => $positionCounter]);
                        $submittedImageIds[] = $existing->id;
                    }
                }

                $positionCounter++;
            }

            //  Excluir imagens removidas no front
            $imagesToDelete = array_diff($currentImageIds, $submittedImageIds);

            if (!empty($imagesToDelete)) {
                $imagesToDeleteData = $currentImages->whereIn("id", $imagesToDelete);

                foreach ($imagesToDeleteData as $img) {
                    if ($img->url) {
                        Storage::disk("public")->delete(str_replace("/storage/", "", $img->url));
                    }
                }

                $product->images()->whereIn("id", $imagesToDelete)->delete();
            }

            //  Inserir novas imagens
            if (!empty($newImagesToCreate)) {
                $product->images()->insert($newImagesToCreate);
            }
        }

        // ====== Recarrega as relações ======
        return $product->load('variants', 'images', 'kits.items.variant');
    });
}


    /**
     * Duplica um produto completo com todas suas relações
     * - Copia produto com nome "copia_nome_original"
     * - Copia todas as variantes com novos SKUs
     * - Duplica arquivos físicos das imagens
     * - Copia kits e seus itens
     */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            // Carrega todas as relações necessárias
            $product->load(['variants.images', 'images', 'kits.items', 'category']);

            // ====== 1. Criar cópia do produto ======
            $newProductData = $product->toArray();

            // Remove campos que não devem ser copiados
            unset($newProductData['id'], $newProductData['created_at'], $newProductData['updated_at'], $newProductData['deleted_at']);
            unset($newProductData['variants'], $newProductData['images'], $newProductData['kits'], $newProductData['category']);

            // Gera nome único com prefixo "Cópia de"
            $baseName = 'Cópia de ' . $product->name;
            $newName = $baseName;
            $counter = 1;

            // Garante que o nome seja único
            while ($this->model->where('name', $newName)->exists()) {
                $newName = $baseName . ' (' . $counter++ . ')';
            }
            $newProductData['name'] = $newName;

            // Gera novo slug único baseado no nome
            $baseSlug = Str::slug($newName);
            $slug = $baseSlug;
            $slugCounter = 1;
            while ($this->model->withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $slugCounter++;
            }
            $newProductData['slug'] = $slug;

            // Cria o novo produto
            $newProduct = $this->model->create($newProductData);

            // ====== 2. Copiar variantes ======
            $variantMap = []; // Mapeia variant_id antigo -> novo
            $skuMap = []; // Mapeia SKU antigo -> novo

            $usedSkusInBatch = []; // Rastreia SKUs já gerados nesta duplicação

            foreach ($product->variants as $variant) {
                $variantData = $variant->toArray();
                unset($variantData['id'], $variantData['created_at'], $variantData['updated_at'], $variantData['product_id']);

                // Gera novo SKU baseado no novo nome do produto
                $oldSku = $variant->sku;
                $slugBase = Str::slug($newProduct->name);
                $colorSlug = Str::slug($variant->color ?? '');
                $sizeSlug = Str::slug($variant->size ?? '');
                $newSku = strtoupper("{$slugBase}-{$colorSlug}-{$sizeSlug}");

                // Garante SKU único: verifica no banco E nos SKUs já gerados nesta iteração
                $skuCounter = 1;
                while (
                    in_array($newSku, $usedSkusInBatch) ||
                    \App\Models\ProductVariant::where('sku', $newSku)->exists()
                ) {
                    $newSku = strtoupper("{$slugBase}-{$colorSlug}-{$sizeSlug}-{$skuCounter}");
                    $skuCounter++;
                }

                $usedSkusInBatch[] = $newSku;
                $variantData['sku'] = $newSku;
                $variantData['product_id'] = $newProduct->id;

                $newVariant = $newProduct->variants()->create($variantData);

                // Mapeia IDs antigos -> novos
                $variantMap[$variant->id] = $newVariant->id;
                $skuMap[$oldSku] = $newSku;
            }

            // ====== 3. Copiar imagens (duplicando arquivos físicos) ======
            foreach ($product->images as $image) {
                // Copia o arquivo físico
                $oldPath = str_replace('/storage/', '', $image->url);
                $newPath = null;

                if (Storage::disk('public')->exists($oldPath)) {
                    $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                    $newFileName = Str::random(40) . '.' . $extension;
                    $newPath = 'products/' . $newFileName;

                    // Copia o arquivo
                    Storage::disk('public')->copy($oldPath, $newPath);
                } else {
                    // Se arquivo não existe, pula esta imagem
                    continue;
                }

                // Cria registro da nova imagem
                $imageData = [
                    'id' => (string) Str::uuid(),
                    'product_id' => $newProduct->id,
                    'variant_id' => $image->variant_id ? ($variantMap[$image->variant_id] ?? null) : null,
                    'url' => Storage::url($newPath),
                    'is_main' => $image->is_main,
                    'position' => $image->position ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $newProduct->images()->insert([$imageData]);
            }

            // ====== 4. Copiar kits e seus itens ======
            foreach ($product->kits as $kit) {
                $kitData = $kit->toArray();
                unset($kitData['id'], $kitData['created_at'], $kitData['updated_at'], $kitData['product_id'], $kitData['items']);

                $kitData['product_id'] = $newProduct->id;

                $newKit = $newProduct->kits()->create($kitData);

                // Copiar itens do kit
                foreach ($kit->items as $item) {
                    $itemData = $item->toArray();
                    unset($itemData['id'], $itemData['created_at'], $itemData['updated_at'], $itemData['kit_id']);

                    // Mapeia variant_id antigo para novo
                    if ($item->variant_id && isset($variantMap[$item->variant_id])) {
                        $itemData['variant_id'] = $variantMap[$item->variant_id];
                    }

                    $itemData['kit_id'] = $newKit->id;
                    $newKit->items()->create($itemData);
                }
            }

            // ====== Recarrega todas as relações e retorna ======
            return $newProduct->load(['variants.images', 'images', 'kits.items.variant', 'category']);
        });
    }

    public function destroy(Product $product): void
    {
        $product->delete();
    }
}