<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedKitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'total_quantity' => $this->total_quantity,

            // Informações do produto
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ],

            // Link para acessar o produto com kit selecionado
            'product_link' => $this->product_link,

            // Imagem principal do produto (para exibir o kit)
            'image' => $this->main_image,

            // Flag indicando que é destaque
            'is_featured' => true,
            'type' => 'kit', // Para diferenciar de produtos normais
        ];
    }
}
