<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'reference' => $this->reference,
            'description' => $this->description,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'ml_price' => $this->ml_price,
            'wholesale_min_qty' => $this->wholesale_min_qty,
            'is_highlighted' => $this->is_highlighted,
            'is_promotion' => $this->is_promotion,
            'promotion_price' => $this->promotion_price,
            'promotion_percent' => $this->promotion_percent,
            'is_new' => $this->is_new,
            'is_new_collection' => $this->is_new_collection,
            'active' => $this->active,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ],
            'kits' => ProductKitResource::collection($this->whenLoaded('kits')),
            'measurement_image' => $this->measurement_image,
            'measurements' => ProductMeasurementResource::collection($this->whenLoaded('measurements')),

        ];        
    }
}
