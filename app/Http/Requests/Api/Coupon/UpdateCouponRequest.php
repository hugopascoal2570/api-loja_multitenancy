<?php

namespace App\Http\Requests\Api\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($couponId)],
            'type' => 'sometimes|in:fixed,percentage',
            'value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'sometimes|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Este código de cupom já existe.',
            'type.in' => 'Tipo inválido. Use "fixed" ou "percentage".',
            'value.min' => 'O valor deve ser maior ou igual a zero.',
            'max_uses_per_user.min' => 'O valor mínimo é 1.',
            'valid_until.after_or_equal' => 'A data de expiração deve ser igual ou posterior à data de início.',
        ];
    }
}
