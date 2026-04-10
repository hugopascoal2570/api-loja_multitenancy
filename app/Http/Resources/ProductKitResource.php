<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductKitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'total_quantity' => $this->total_quantity,
            'price' => $this->price,
            'fixed_size' => $this->fixed_size,
            'fixed_color' => $this->fixed_color,
            'description' => $this->description,
            'is_featured' => $this->is_featured,
            'is_redistributed' => $this->is_redistributed,
            'is_active' => $this->is_active,
            'redistributed_at' => $this->redistributed_at,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'items' => ProductKitItemResource::collection($this->whenLoaded('items')),
            'original_items' => ProductKitItemResource::collection($this->whenLoaded('originalItems')),
        ];
    }
}
