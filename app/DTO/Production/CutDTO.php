<?php

namespace App\DTO\Production;

class CutDTO
{
    public function __construct(
        public ?float $cutting_labor_cost,
        public string $status,
        public ?string $notes,
        public array $fabricRolls = [],
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            cutting_labor_cost: $request->cutting_labor_cost,
            status: $request->status ?? 'pending',
            notes: $request->notes,
            fabricRolls: collect($request->input('fabric_rolls', []))->map(function ($roll) {
                return [
                    'color' => $roll['color'],
                    'quantity_rolls' => $roll['quantity_rolls'] ?? 1,
                    'meters' => $roll['meters'],
                    'price_per_roll' => $roll['price_per_roll'] ?? null,
                    'price_per_meter' => $roll['price_per_meter'] ?? null,
                    'notes' => $roll['notes'] ?? null,
                ];
            })->toArray(),
        );
    }
}
