<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreFabricRollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color' => 'required|string|max:100',
            'quantity_rolls' => 'nullable|integer|min:1',
            'meters' => 'required|numeric|min:0.01',
            'price_per_roll' => 'nullable|numeric|min:0',
            'price_per_meter' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $pricePerRoll = $this->input('price_per_roll');
            $pricePerMeter = $this->input('price_per_meter');

            // Precisa ter pelo menos um dos precos
            if (empty($pricePerRoll) && empty($pricePerMeter)) {
                $validator->errors()->add('price_per_roll', 'Informe o preco total (price_per_roll) ou o preco por metro (price_per_meter).');
            }
        });
    }
}
