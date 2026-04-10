<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'min:3', 'max:255'],
            'last_name'    => ['required', 'string', 'min:2', 'max:255'],
            'email'        => ['required', 'email', 'min:3', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'min:8', 'max:100'],
            'cpf'          => ['nullable', 'string', 'unique:users,cpf'],
            'phone'        => ['nullable', 'string', 'max:20'],
            
            // Endereço
            'address'      => ['nullable', 'string'],
            'number'       => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string'],
            'complement'   => ['nullable', 'string'],
            'city'         => ['nullable', 'string'],
            'state'        => ['nullable', 'string'],
            'zip_code'     => ['nullable', 'string', 'max:10'],

        ];
    }
}