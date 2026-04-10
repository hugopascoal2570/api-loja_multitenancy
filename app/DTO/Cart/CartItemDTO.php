<?php

namespace App\DTO\Cart;

class CartItemDTO
{
    public function __construct(
        public readonly ?string $product_id = null,
        public readonly ?string $type = null,
        public readonly ?string $variant_id = null,
        public readonly ?string $kit_id = null,
        public readonly int $quantity,
        public readonly float $unit_price = 0,
        public readonly float $total_price = 0
    ) {
    }
}