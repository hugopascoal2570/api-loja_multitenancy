<?php

// app/Http/Resources/ProductVariantResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'size' => $this->size,
            'color' => $this->color,
            'stock' => $this->stock,
            'sku' => $this->sku,
            // Imagens específicas desta variante
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            // URL da imagem principal desta variante (ou fallback para produto)
            'image_url' => $this->getVariantImageUrl(),
        ];
    }
}
