<?php

namespace App\Http\Requests\Api\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código do cupom é obrigatório.',
        ];
    }
}
