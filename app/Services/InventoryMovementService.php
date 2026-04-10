<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;

class InventoryMovementService
{
    public function recordMovement(
        ProductVariant $variant,
        string $type,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        string $reason,
        ?string $orderId = null,
        ?string $notes = null,
        ?string $userId = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'related_order_id' => $orderId,
            'notes' => $notes,
            'user_id' => $userId,
        ]);
    }
}
