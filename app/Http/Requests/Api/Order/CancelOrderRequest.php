<?php

namespace App\Http\Requests\Api\Order;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ajuste se houver policy; por padrão, deixa true
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable','numeric','min:0.01'],
            'reason' => ['nullable','string','max:1000'],
        ];
    }
}
