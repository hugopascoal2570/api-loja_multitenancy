<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeamstressAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seamstress_id' => 'required|uuid|exists:seamstresses,id',
            'cut_production_id' => 'required|uuid|exists:cut_productions,id',
            'quantity_assigned' => 'required|integer|min:1',
            'price_per_piece' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
