<?php

namespace App\Http\Requests\Api\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'retail_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'ml_price' => 'nullable|numeric|min:0',
            'wholesale_min_qty' => 'nullable|integer|min:1',
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],

            'variants' => 'sometimes|required|array|min:1',
            'variants.*.size' => 'sometimes|required|string|max:10',
            'variants.*.color' => 'sometimes|required|string|max:50',
            'variants.*.stock' => 'sometimes|required|integer|min:0',
            'variants.*.sku' => [
                'nullable', 'string', 'max:50', 'distinct',
                Rule::unique('product_variants', 'sku')->where(function ($query) {
                    // Ignora variantes do próprio produto sendo editado
                    $productId = $this->route('product')?->id ?? $this->route('product');
                    if ($productId) {
                        $query->where('product_id', '!=', $productId);
                    }
                }),
            ],

            'images' => ['sometimes', 'array'],
            'images.*.id' => ['nullable', 'uuid', 'exists:product_images,id'], // Para identificar imagens existentes
            'images.*.image' => ['nullable', 'file', 'image', 'max:2048'], // Não é required para imagens existentes
            'images.*.url' => ['nullable', 'string'], // Para imagens existentes que não são reenviadas como arquivos
            'images.*.is_main' => ['nullable', 'in:true,false,1,0'],
            'images.*.variant_sku' => ['nullable', 'string'],

            'is_highlighted' => ['sometimes', 'required', 'in:true,false,1,0'],
            'is_promotion' => ['sometimes', 'required', 'in:true,false,1,0'],
            'promotion_price' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                $retailPrice = $this->input('retail_price');
                if ($retailPrice && $value >= $retailPrice) {
                    $fail('O preço de promoção deve ser menor que o preço de varejo.');
                }
            }],

            'is_new' => ['sometimes', 'required', 'in:true,false,1,0'],
            'is_new_collection' => ['sometimes', 'required', 'in:true,false,1,0'],
            'active' => ['sometimes', 'required', 'in:true,false,1,0'],

            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
        ];
    }
}