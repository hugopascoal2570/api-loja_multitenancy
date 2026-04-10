<?php

// app/Repositories/InventoryRepository.php

namespace App\Repositories;

use App\DTO\Inventory\InventoryDTO;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryMovementService;
use App\Services\KitStockRedistributionService;
use Illuminate\Support\Collection;

class InventoryRepository
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected KitStockRedistributionService $kitStockService,
    ) {}

    public function showByProduct(Product $product): Collection
    {
        return $product->variants()->select('id', 'size', 'color', 'stock', 'sku')->get();
    }

    public function showByVariant(ProductVariant $variant): ProductVariant
    {
        return $variant;
    }

    public function add(ProductVariant $variant, InventoryDTO $data): ProductVariant
    {
        $stockBefore = $variant->stock;
        $variant->increment('stock', $data->quantity);
        $variant->refresh();

        // Registra a movimentação
        $this->movementService->recordMovement(
            $variant,
            'in',
            $data->quantity,
            $stockBefore,
            $variant->stock,
            'manual_add',
            null,
            null,
            auth()->id()
        );

        // Verificar redistribuição de kits
        $this->kitStockService->handleStockChange($variant);

        return $variant;
    }

    public function remove(ProductVariant $variant, InventoryDTO $data): ProductVariant
    {
        $stockBefore = $variant->stock;
        $variant->decrement('stock', $data->quantity);
        $variant->refresh();

        // Registra a movimentação
        $this->movementService->recordMovement(
            $variant,
            'out',
            $data->quantity,
            $stockBefore,
            $variant->stock,
            'manual_remove',
            null,
            null,
            auth()->id()
        );

        // Verificar redistribuição de kits
        $this->kitStockService->handleStockChange($variant);

        return $variant;
    }

    public function set(ProductVariant $variant, InventoryDTO $data): ProductVariant
    {
        $stockBefore = (int) $variant->stock;
        $newQuantity = (int) $data->quantity;

        // Sem mudança real — não registra movimento nem dispara jobs
        if ($stockBefore === $newQuantity) {
            return $variant;
        }

        $variant->update(['stock' => $newQuantity]);
        $variant->refresh();

        // Calcula a diferença
        $difference = $newQuantity - $stockBefore;
        $type = $difference > 0 ? 'in' : 'out';
        $quantity = abs($difference);

        // Registra a movimentação
        $this->movementService->recordMovement(
            $variant,
            $type,
            $quantity,
            $stockBefore,
            $variant->stock,
            'manual_set',
            null,
            null,
            auth()->id()
        );

        // Verificar redistribuição de kits
        $this->kitStockService->handleStockChange($variant);

        return $variant;
    }
}
