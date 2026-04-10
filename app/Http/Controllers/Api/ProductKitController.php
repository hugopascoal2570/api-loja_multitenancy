<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductKit;
use App\Repositories\ProductKitRepository;
use App\Http\Requests\Api\Product\StoreProductKitRequest;
use App\Http\Requests\Api\Product\UpdateProductKitRequest;
use App\Http\Resources\ProductKitResource;
use App\Http\Resources\ProductVariantResource;

class ProductKitController extends Controller
{
    protected ProductKitRepository $repository;

    public function __construct(ProductKitRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Cria um novo kit para o produto.
     */
    public function store(StoreProductKitRequest $request, Product $product)
    {
        $kit = $this->repository->create($product, $request->validated());

        return response()->json([
            'message' => 'Kit criado com sucesso.',
            'data' => $kit
        ], 201);
    }

    /**
     * Atualiza um kit existente.
     */
    public function update(UpdateProductKitRequest $request, ProductKit $kit)
    {
        $kit = $this->repository->update($kit, $request->validated());

        return response()->json([
            'message' => 'Kit atualizado com sucesso.',
            'data' => $kit
        ]);
    }

    /**
     * Remove um kit.
     */
    public function destroy(ProductKit $kit)
    {
        $this->repository->destroy($kit);

        return response()->json(['message' => 'Kit removido com sucesso.']);
    }

    /**
     * Lista todas as variantes do produto.
     */
    public function variants(Product $product)
    {
        return ProductVariantResource::collection(
            $product->variants()->orderBy('size')->orderBy('color')->get()
        );
    }

    /**
     * Lista todos os kits do produto.
     */
    public function kits(Product $product)
    {
        $kits = $product->kits()->with(['items.variant', 'originalItems.variant'])->get();

        return ProductKitResource::collection($kits);
    }
}
