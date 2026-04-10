<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'url'         => $this->url,
            'full_url'    => $this->url ? url($this->url) : null,
            'is_main'     => (bool) $this->is_main,
            'position'    => $this->position,
            'variant_sku' => $this->variant?->sku ?? null,
        ];
    }
}

