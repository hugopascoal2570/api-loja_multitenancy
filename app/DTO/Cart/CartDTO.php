<?php

namespace App\DTO\Cart;

class CartDTO
{
    public function __construct(
        public string $token,
        public ?string $user_id = null,
        public string $status = 'open',
        public ?int $total_items = 0,
        public ?float $subtotal = 0.0,
    ) {}
}

