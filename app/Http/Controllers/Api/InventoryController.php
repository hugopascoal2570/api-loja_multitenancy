<?php

namespace App\Http\Controllers\Api;

use App\DTO\Inventory\InventoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\UpdateInventoryRequest;
use App\Http\Resources\InventoryVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\InventoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryRepository $repository
    ) {}

    /**
     * Lista todos os produtos ativos com variantes e estoque atual
     * GET /inventory/all
     */
    public function all(): JsonResponse
    {
        $products = Product::where('active', true)
            ->with(['variants' => fn($q) => $q->orderBy('color')->orderBy('size')])
            ->orderBy('name')
            ->get();

        $data = $products->map(fn($product) => [
            'id'       => $product->id,
            'name'     => $product->name,
            'variants' => InventoryVariantResource::collection($product->variants),
        ]);

        return response()->json($data);
    }

    /**
     * Regularização em massa: informa o novo total de cada variante
     * POST /inventory/bulk-set
     * Body: { "items": [{"variant_id": "uuid", "quantity": 10}, ...] }
     */
    public function bulkSet(Request $request): JsonResponse
    {
        $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid|exists:product_variants,id',
            'items.*.quantity'  => 'required|integer|min:0',
        ]);

        $updated  = [];
        $skipped  = [];

        DB::transaction(function () use ($request, &$updated, &$skipped) {
            foreach ($request->items as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                $newQty  = (int) $item['quantity'];

                // Sem mudança — pula
                if ($variant->stock === $newQty) {
                    $skipped[] = $variant->sku;
                    continue;
                }

                $dto     = new InventoryDTO(quantity: $newQty);
                $variant = $this->repository->set($variant, $dto);

                $updated[] = [
                    'variant_id' => $variant->id,
                    'sku'        => $variant->sku,
                    'color'      => $variant->color,
                    'size'       => $variant->size,
                    'stock'      => $variant->stock,
                ];
            }
        });

        return response()->json([
            'message' => count($updated) . ' variante(s) atualizadas, ' . count($skipped) . ' sem alteração.',
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function showByProduct(Product $product): JsonResponse
    {
        $variants = $this->repository->showByProduct($product);
        return response()->json(InventoryVariantResource::collection($variants));
    }

    public function showByVariant(ProductVariant $variant): InventoryVariantResource
    {
        return new InventoryVariantResource($this->repository->showByVariant($variant));
    }

    public function addStock(UpdateInventoryRequest $request, ProductVariant $variant): InventoryVariantResource
    {
        $dto = new InventoryDTO(...$request->validated());
        $variant = $this->repository->add($variant, $dto);

        return new InventoryVariantResource($variant);
    }

    public function removeStock(UpdateInventoryRequest $request, ProductVariant $variant): InventoryVariantResource
    {
        $dto = new InventoryDTO(...$request->validated());
        $variant = $this->repository->remove($variant, $dto);

        return new InventoryVariantResource($variant);
    }

    public function setStock(UpdateInventoryRequest $request, ProductVariant $variant): InventoryVariantResource
    {
        $dto = new InventoryDTO(...$request->validated());
        $variant = $this->repository->set($variant, $dto);

        return new InventoryVariantResource($variant);
    }
}
