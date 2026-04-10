<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FabricRollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cut_id' => $this->cut_id,
            'color' => $this->color,
            'quantity_rolls' => $this->quantity_rolls ?? 1,
            'meters' => (float) $this->meters,
            'average_meters_per_roll' => (float) $this->average_meters_per_roll,
            'price_per_meter' => (float) $this->price_per_meter,
            'price_per_roll' => (float) $this->price_per_roll,
            'total_price' => (float) $this->total_price,
            'cost_per_meter' => (float) $this->cost_per_meter,
            'meters_used' => (float) $this->meters_used,
            'meters_remaining' => (float) $this->meters_remaining,
            'notes' => $this->notes,

            // Simplified to avoid circular reference
            'cut' => $this->when($this->relationLoaded('cut') && $this->cut, [
                'id' => $this->cut?->id,
                'cut_number' => $this->cut?->cut_number,
                'status' => $this->cut?->status,
            ]),
            'productions' => $this->when($this->relationLoaded('productions'), function () {
                return $this->productions->map(fn($p) => [
                    'id' => $p->id,
                    'product_description' => $p->product_description,
                    'quantity_produced' => $p->quantity_produced,
                    'fabric_meters_used' => (float) $p->fabric_meters_used,
                ]);
            }),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
