<?php

namespace App\Http\Requests\Api\Product;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'total_quantity' => 'required|integer|min:1',
            'fixed_color' => 'nullable|string|max:100',
            'fixed_size' => 'nullable|string|max:100',
            'is_featured' => 'nullable|boolean',
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $total = array_sum(array_column($items, 'quantity'));

            if ($total != $this->input('total_quantity')) {
                $validator->errors()->add(
                    'items',
                    'A soma das quantidades dos itens deve ser igual à quantidade total do kit (' . $this->input('total_quantity') . ').'
                );
            }

            // Validar estoque > 0 para cada variante
            foreach ($items as $index => $item) {
                if (!empty($item['variant_id'])) {
                    $variant = ProductVariant::find($item['variant_id']);
                    if ($variant && ($variant->stock === null || $variant->stock <= 0)) {
                        $validator->errors()->add(
                            "items.{$index}.variant_id",
                            "A variante {$variant->size}/{$variant->color} possui estoque zerado e não pode ser incluída no kit."
                        );
                    }
                }
            }
        });
    }
}
