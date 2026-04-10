<?php

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '*.product_id' => ['required', 'uuid', 'exists:products,id'],
            '*.type' => ['required', Rule::in(['variant', 'kit'])],
            '*.variant_id' => [
                'required_if:*.type,variant',
                'nullable',
                'uuid',
                'exists:product_variants,id'
            ],
            '*.kit_id' => [
                'required_if:*.type,kit',
                'nullable',
                'uuid',
                'exists:product_kits,id'
            ],
            '*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.product_id.required' => 'O campo product_id é obrigatório.',
            '*.product_id.uuid' => 'O campo product_id deve ser um UUID válido.',
            '*.product_id.exists' => 'O produto informado não existe.',

            '*.type.required' => 'O campo type é obrigatório.',
            '*.type.in' => 'O tipo deve ser "variant" ou "kit".',

            '*.variant_id.required_if' => 'O campo variant_id é obrigatório quando o tipo for "variant".',
            '*.variant_id.uuid' => 'O campo variant_id deve ser um UUID válido.',
            '*.variant_id.exists' => 'A variação informada não existe.',

            '*.kit_id.required_if' => 'O campo kit_id é obrigatório quando o tipo for "kit".',
            '*.kit_id.uuid' => 'O campo kit_id deve ser um UUID válido.',
            '*.kit_id.exists' => 'O kit informado não existe.',

            '*.quantity.required' => 'O campo quantity é obrigatório.',
            '*.quantity.integer' => 'O campo quantity deve ser um número inteiro.',
            '*.quantity.min' => 'A quantidade mínima é 1.',
        ];
    }
}
