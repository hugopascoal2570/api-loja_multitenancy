<?php

namespace App\DTO\Production;

class SeamstressDTO
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $address,
        public float $price_per_piece,
        public bool $is_active,
        public ?string $notes,
        public array $costs = [],
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            name: $request->name,
            phone: $request->phone,
            address: $request->address,
            price_per_piece: $request->price_per_piece ?? 0,
            is_active: filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN),
            notes: $request->notes,
            costs: collect($request->input('costs', []))->map(function ($cost) {
                return [
                    'name' => $cost['name'],
                    'price' => $cost['price'],
                    'cost_type' => $cost['cost_type'] ?? 'per_piece',
                    'is_active' => filter_var($cost['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'notes' => $cost['notes'] ?? null,
                ];
            })->toArray(),
        );
    }
}
