<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:3',
                'max:255',
            ],
            'password' => [
                'nullable',
                'min:8',
                'max:100',
            ],
            'last_name' => ['nullable', 'string'],
            'cpf' => ['nullable', 'string', Rule::unique('users', 'cpf')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string'],

            'address' => ['nullable', 'string'],
            'number' => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string'],
            'complement' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'zip_code' => ['nullable', 'string'],
        ];
    }
}
