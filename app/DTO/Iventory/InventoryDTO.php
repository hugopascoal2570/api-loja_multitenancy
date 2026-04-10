<?php

namespace App\DTO\Inventory;

class InventoryDTO
{
    public function __construct(
        public int $quantity
    ) {}
}
