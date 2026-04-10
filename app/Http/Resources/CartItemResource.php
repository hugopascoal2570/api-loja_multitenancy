<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->product;
        $variant = $this->variant;
        $kit = $this->kit;

        // Obter imagem principal (is_main = 1), senão a primeira
        $imageUrl = null;
        if ($product && $product->relationLoaded('images') && $product->images->isNotEmpty()) {
            $mainImage = $product->images->firstWhere('is_main', 1) ?? $product->images->first();
            $imageUrl = $mainImage ? $mainImage->url : null;
        }

        // Nome do item (produto, variante ou kit)
        $name = $product ? $product->name : 'Produto';
        
        // Se for kit, usa o nome do kit
        if ($this->type === 'kit' && $kit) {
            $name = $kit->name;
        }

        // Informações da variante (tamanho e cor)
        $size = null;
        $color = null;
        if ($this->type === 'variant' && $variant) {
            $size = $variant->size;
            $color = $variant->color;
        }

        // Verifica se está com preço de atacado
        $isWholesale = false;
        $wholesaleMinQty = null;
        if ($product && $product->wholesale_price && $product->wholesale_min_qty) {
            $wholesaleMinQty = $product->wholesale_min_qty;
            $isWholesale = (float) $this->unit_price === (float) $product->wholesale_price;
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'type' => $this->type,
            'variant_id' => $this->variant_id,
            'kit_id' => $this->kit_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'image_url' => $imageUrl,
            'name' => $name,
            'size' => $size,
            'color' => $color,
            'is_wholesale' => $isWholesale,
            'wholesale_min_qty' => $wholesaleMinQty,
            'retail_price' => $product?->retail_price,
            'wholesale_price' => $product?->wholesale_price,
        ];
    }
}
