<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCutRequest extends FormRequest
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
        ];
    }
}
