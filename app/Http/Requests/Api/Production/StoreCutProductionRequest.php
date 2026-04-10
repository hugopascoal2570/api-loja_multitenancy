<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreCutProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fabric_roll_id' => 'required|uuid|exists:fabric_rolls,id',
            'product_id' => 'nullable|uuid|exists:products,id',
            'product_variant_id' => 'nullable|uuid|exists:product_variants,id',
            'product_description' => 'nullable|string|max:255',
            'quantity_produced' => 'required|integer|min:1',
            'fabric_meters_used' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
