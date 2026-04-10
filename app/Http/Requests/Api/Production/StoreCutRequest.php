<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreCutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cutting_labor_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,in_progress,completed',
            'notes' => 'nullable|string|max:1000',

            'fabric_rolls' => 'nullable|array',
            'fabric_rolls.*.color' => 'required|string|max:100',
            'fabric_rolls.*.quantity_rolls' => 'nullable|integer|min:1',
            'fabric_rolls.*.meters' => 'required|numeric|min:0.01',
            'fabric_rolls.*.price_per_roll' => 'nullable|numeric|min:0',
            'fabric_rolls.*.price_per_meter' => 'nullable|numeric|min:0',
            'fabric_rolls.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fabricRolls = $this->input('fabric_rolls', []);

            foreach ($fabricRolls as $index => $roll) {
                $pricePerRoll = $roll['price_per_roll'] ?? null;
                $pricePerMeter = $roll['price_per_meter'] ?? null;

                if (empty($pricePerRoll) && empty($pricePerMeter)) {
                    $validator->errors()->add(
                        "fabric_rolls.{$index}.price_per_roll",
                        'Informe o preco total ou o preco por metro para cada rolo.'
                    );
                }
            }
        });
    }
}
