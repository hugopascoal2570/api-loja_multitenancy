<?php

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;

class RecordReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_returned' => 'required|integer|min:1',
            'quantity_defective' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
