<?php

namespace App\Http\Requests\Api\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código do cupom é obrigatório.',
            'code.unique' => 'Este código de cupom já existe.',
            'type.required' => 'O tipo de desconto é obrigatório.',
            'type.in' => 'Tipo inválido. Use "fixed" ou "percentage".',
            'value.required' => 'O valor do desconto é obrigatório.',
            'value.min' => 'O valor deve ser maior ou igual a zero.',
            'max_uses_per_user.required' => 'Informe quantas vezes cada usuário pode usar este cupom.',
            'max_uses_per_user.min' => 'O valor mínimo é 1.',
            'valid_until.after_or_equal' => 'A data de expiração deve ser igual ou posterior à data de início.',
        ];
    }
}
