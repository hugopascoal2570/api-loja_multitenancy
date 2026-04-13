<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'added_at'   => $this->created_at->toDateTimeString(),
            'product'    => [
                'id'               => $product->id,
                'slug'             => $product->slug,
                'name'             => $product->name,
                'retail_price'     => $product->retail_price,
                'is_promotion'     => (bool) $product->is_promotion,
                'promotion_price'  => $product->is_promotion ? $product->promotion_price : null,
                'promotion_percent'=> $product->is_promotion ? $product->promotion_percent : null,
                'active'           => (bool) $product->active,
                'image_url'        => $product->images->first()?->image_url ?? null,
            ],
        ];
    }
}
