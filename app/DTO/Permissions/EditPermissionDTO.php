<?php

namespace App\DTO\Permissions;

class EditPermissionDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
    ) {
    }
}
