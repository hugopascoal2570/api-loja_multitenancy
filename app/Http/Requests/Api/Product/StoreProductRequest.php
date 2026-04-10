<?php

namespace App\Http\Requests\Api\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'retail_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'ml_price' => 'nullable|numeric|min:0',
            'wholesale_min_qty' => 'nullable|integer|min:1',
            'category_id' => ['required', 'exists:categories,id'],

            'variants' => 'required|array|min:1',
            'variants.*.size' => 'required|string|max:10',
            'variants.*.color' => 'required|string|max:50',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.sku' => 'nullable|string|max:50|distinct|unique:product_variants,sku',

            'images' => ['required', 'array'],
            'images.*.id' => ['nullable', 'uuid', 'exists:product_images,id'],
            'images.*.image' => ['nullable', 'file', 'image', 'max:2048'],
            'images.*.url' => ['nullable', 'string'],
            'images.*.is_main' => ['nullable', 'in:true,false,1,0'],
            'images.*.variant_sku' => ['nullable', 'string'],

            'is_highlighted' => ['required', 'in:true,false,1,0'],
            'is_promotion' => ['required', 'in:true,false,1,0'],
            'promotion_price' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                $retailPrice = $this->input('retail_price');
                if ($retailPrice && $value >= $retailPrice) {
                    $fail('O preço de promoção deve ser menor que o preço de varejo.');
                }
            }],

            'is_new' => ['required', 'in:true,false,1,0'],
            'is_new_collection' => ['required', 'in:true,false,1,0'],
            'kits' => 'nullable|array',
            'kits.*.name' => 'nullable|string|max:255',
            'kits.*.total_quantity' => 'required|integer|min:1',
            'kits.*.price' => 'required|numeric|min:0',
            'kits.*.fixed_size' => 'nullable|string|max:10',
            'kits.*.fixed_color' => 'nullable|string|max:50',
            'kits.*.items' => 'nullable|array',
            'kits.*.items.*.variant_id' => 'required|exists:product_variants,id',
            'kits.*.items.*.quantity' => 'required|integer|min:1',

            'active' => ['required', 'in:true,false,1,0'],

            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
        ];
    }
}