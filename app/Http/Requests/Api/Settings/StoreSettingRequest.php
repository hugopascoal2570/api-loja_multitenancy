<?php

namespace App\Http\Requests\Api\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'         => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'type'        => ['nullable', 'in:string,color,image,boolean'],
            'value'       => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'image' && $this->hasFile('value')) {
                        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        if (!in_array($this->file('value')->getMimeType(), $allowed)) {
                            $fail('O arquivo deve ser uma imagem válida (jpeg, png, webp ou gif).');
                        }
                    }
                },
            ],
            'group'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
