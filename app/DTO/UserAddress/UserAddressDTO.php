<?php

namespace App\DTO\UserAddress;

class UserAddressDTO
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $user_id = null,
        public readonly ?string $label = null,
        public readonly ?string $recipient_name = null,
        public readonly string $address = '',
        public readonly string $number = '',
        public readonly string $neighborhood = '',
        public readonly ?string $complement = null,
        public readonly string $city = '',
        public readonly string $state = '',
        public readonly string $zip_code = '',
        public readonly ?string $phone = null,
        public readonly bool $is_default = false,
    ) {}
}
