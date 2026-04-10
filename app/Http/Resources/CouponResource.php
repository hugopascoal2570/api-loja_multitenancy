<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'type_label' => $this->type === 'fixed' ? 'Valor Fixo' : 'Porcentagem',
            'value' => (float) $this->value,
            'formatted_value' => $this->type === 'fixed'
                ? 'R$ ' . number_format($this->value, 2, ',', '.')
                : $this->value . '%',
            'max_uses' => $this->max_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'current_uses' => $this->current_uses,
            'remaining_uses' => $this->max_uses ? max(0, $this->max_uses - $this->current_uses) : null,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
