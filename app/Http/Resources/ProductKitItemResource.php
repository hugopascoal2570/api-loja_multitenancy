<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductKitItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
            'variant' => [
                'size' => $this->variant?->size,
                'color' => $this->variant?->color,
            ],
        ];
    }
}
