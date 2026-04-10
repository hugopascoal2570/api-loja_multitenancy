<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CutProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cut_id' => $this->cut_id,
            'fabric_roll_id' => $this->fabric_roll_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_description' => $this->product_description,
            'product_display_name' => $this->product_display_name,
            'quantity_produced' => $this->quantity_produced,
            'fabric_meters_used' => (float) $this->fabric_meters_used,
            'notes' => $this->notes,

            // Calculated fields
            'fabric_cost' => (float) $this->fabric_cost,
            'fabric_cost_per_piece' => (float) $this->fabric_cost_per_piece,
            'cutting_cost_per_piece' => (float) $this->cutting_cost_per_piece,
            'quantity_assigned' => $this->quantity_assigned,
            'quantity_available' => $this->quantity_available,

            // Relationships (simplified to avoid circular reference)
            'cut' => $this->when($this->relationLoaded('cut') && $this->cut, [
                'id' => $this->cut?->id,
                'cut_number' => $this->cut?->cut_number,
                'status' => $this->cut?->status,
            ]),
            'fabric_roll' => $this->when($this->relationLoaded('fabricRoll') && $this->fabricRoll, [
                'id' => $this->fabricRoll?->id,
                'color' => $this->fabricRoll?->color,
                'meters' => (float) $this->fabricRoll?->meters,
                'price_per_meter' => (float) $this->fabricRoll?->price_per_meter,
            ]),
            'product' => $this->when($this->relationLoaded('product') && $this->product, [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'slug' => $this->product?->slug,
            ]),
            'product_variant' => $this->when($this->relationLoaded('productVariant') && $this->productVariant, [
                'id' => $this->productVariant?->id,
                'color' => $this->productVariant?->color,
                'size' => $this->productVariant?->size,
                'sku' => $this->productVariant?->sku,
            ]),
            'assignments' => SeamstressAssignmentResource::collection($this->whenLoaded('assignments')),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
