<?php
namespace App\DTO\Users;

class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $last_name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $cpf = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $number = null,
        public readonly ?string $neighborhood = null,
        public readonly ?string $complement = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zip_code = null,
        public readonly bool $is_admin = false,
    ) {}
}
