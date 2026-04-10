<?php

namespace App\Http\Requests\Api\Banners;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link'        => ['nullable', 'string'],
            'is_featured' => ['sometimes'],
            'position'    => ['sometimes', 'integer', 'min:1'],
            'image'       => ['nullable', 'image', 'max:10240', $this->bannerDimensionsRule()],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'device_type' => ['sometimes', 'in:all,desktop,mobile'],
        ];
    }

    private function bannerDimensionsRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (!$value) return;
            $size = @getimagesize($value->getPathname());
            if (!$size) return;
            [$width, $height] = $size;
            if ($width < 1920 || $height < 800) {
                $fail("A imagem do banner deve ter no mínimo 1920×800 pixels. Imagem enviada: {$width}×{$height}px.");
            }
        };
    }

    public function messages(): array
    {
        return [
            'link.url'       => 'O campo link deve ser uma URL válida.',
            'image.image'    => 'O arquivo deve ser uma imagem.',
            'image.max'      => 'A imagem não pode ultrapassar 10 MB.',
            'position.min'   => 'A posição mínima é 1.',
            'end_date.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
            'device_type' => 'Você deve informar o tipo do banner',
        ];
    }
}
