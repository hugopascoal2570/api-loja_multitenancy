<?php

namespace App\DTO\Settings;

class CreateSettingDTO
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?string $type = 'string',
        public ?string $group = null,
        public ?string $description = null,
    ) {}
}
