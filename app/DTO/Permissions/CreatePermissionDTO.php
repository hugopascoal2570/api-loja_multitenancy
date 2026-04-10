<?php

namespace App\DTO\Permissions;

class CreatePermissionDTO
{
    public function __construct(
        public readonly string $name, 
        public readonly ?string $description = '',
    ) {
    }
}
