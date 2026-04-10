<?php

namespace App\DTO\Category;

class CategoryDTO
    {
        public function __construct(
            public string $name,
            public ?string $description = null
        ) {}
}