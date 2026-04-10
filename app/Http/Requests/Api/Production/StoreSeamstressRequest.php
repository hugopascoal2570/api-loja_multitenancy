<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeamstressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'price_per_piece' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|in:true,false,1,0',
            'notes' => 'nullable|string|max:1000',

            'costs' => 'nullable|array',
            'costs.*.name' => 'required|string|max:100',
            'costs.*.price' => 'required|numeric|min:0',
            'costs.*.cost_type' => 'nullable|in:per_piece,fixed',
            'costs.*.is_active' => 'nullable|in:true,false,1,0',
            'costs.*.notes' => 'nullable|string|max:255',
        ];
    }
}
