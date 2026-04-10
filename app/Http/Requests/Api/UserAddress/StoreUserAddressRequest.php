<?php

namespace App\Http\Requests\Api\UserAddress;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'complement' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:15',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ];
    }
}
