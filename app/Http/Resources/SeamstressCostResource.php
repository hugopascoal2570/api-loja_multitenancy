<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeamstressCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seamstress_id' => $this->seamstress_id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'cost_type' => $this->cost_type,
            'cost_type_label' => $this->cost_type === 'per_piece' ? 'Por Peca' : 'Fixo',
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
