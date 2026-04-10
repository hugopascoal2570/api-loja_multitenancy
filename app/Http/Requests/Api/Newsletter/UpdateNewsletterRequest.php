<?php

namespace App\Http\Requests\Api\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'image.image' => 'O arquivo deve ser uma imagem.',
            'image.mimes' => 'A imagem deve ser JPG, JPEG, PNG ou WebP.',
            'image.max' => 'A imagem não pode ter mais de 2MB.',
        ];
    }
}
