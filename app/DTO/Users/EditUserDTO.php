<?php

namespace App\DTO\Users;

class EditUserDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $last_name = null,
        public readonly ?string $password = null,
        public readonly ?string $cpf = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $number = null,
        public readonly ?string $neighborhood = null,
        public readonly ?string $complement = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zip_code = null,
        public readonly ?bool $is_admin = null
    ) {}
}