<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeliverySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_delivery_enabled' => 'required|boolean',
            'delivery_fee' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'cutoff_day' => 'nullable|string|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'cutoff_time' => 'nullable|date_format:H:i',
            'start_day' => 'nullable|string|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'next_delivery_message' => 'nullable|string|max:500',
            'minimum_order_value' => 'nullable|numeric|min:0',
            'minimum_order_message' => 'nullable|string|max:500',
            'is_pickup_enabled' => 'nullable|boolean',
            'pickup_address' => 'nullable|string|max:1000',
            'pickup_instructions' => 'nullable|string|max:1000',
            'is_dynamic_shipping_enabled' => 'nullable|boolean',
            'store_notice' => 'nullable|string|max:500',
            'is_store_open' => 'nullable|boolean',
            'origin_zip_code' => 'nullable|string|max:9',
            'default_weight' => 'nullable|numeric|min:0.001',
            'default_width' => 'nullable|numeric|min:1',
            'default_height' => 'nullable|numeric|min:1',
            'default_length' => 'nullable|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'is_delivery_enabled.required' => 'O campo "cobrança de entrega ativa" é obrigatório.',
            'is_delivery_enabled.boolean' => 'O campo "cobrança de entrega ativa" deve ser verdadeiro ou falso.',
            'delivery_fee.required' => 'O valor da taxa de entrega é obrigatório.',
            'delivery_fee.numeric' => 'O valor da taxa de entrega deve ser um número.',
            'delivery_fee.min' => 'O valor da taxa de entrega não pode ser negativo.',
            'description.max' => 'A descrição não pode ter mais de 1000 caracteres.',
            'cutoff_day.in' => 'O dia de corte deve ser um dia da semana válido em inglês (ex: friday).',
            'cutoff_time.date_format' => 'O horário de corte deve estar no formato HH:MM (ex: 11:00).',
            'next_delivery_message.max' => 'A mensagem não pode ter mais de 500 caracteres.',
            'is_pickup_enabled.boolean' => 'O campo "retirada no local ativa" deve ser verdadeiro ou falso.',
            'pickup_address.max' => 'O endereço de retirada não pode ter mais de 1000 caracteres.',
            'pickup_instructions.max' => 'As instruções de retirada não podem ter mais de 1000 caracteres.',
            'store_notice.max' => 'O aviso da loja não pode ter mais de 500 caracteres.',
            'is_store_open.boolean' => 'O campo "loja aberta" deve ser verdadeiro ou falso.',
        ];
    }
}
