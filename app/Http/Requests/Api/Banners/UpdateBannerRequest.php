<?php
namespace App\Http\Requests\Api\Banners;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_featured')) {
            $this->merge([
                'is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'link'        => 'nullable|string',
            'is_featured' => 'sometimes|boolean',
            'active'      => 'sometimes|boolean',
            'position'    => 'sometimes|integer',
            'image'       => ['nullable', 'image', 'max:10240', $this->bannerDimensionsRule()],
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'device_type' => 'sometimes|in:all,desktop,mobile',
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
}