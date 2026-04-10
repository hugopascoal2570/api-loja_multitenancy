<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeamstressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'price_per_piece' => (float) $this->price_per_piece,
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // Calculated fields
            'total_extra_costs_per_piece' => (float) $this->total_extra_costs_per_piece,
            'total_fixed_costs' => (float) $this->total_fixed_costs,
            'full_cost_per_piece' => (float) $this->full_cost_per_piece,
            'total_pieces_completed' => $this->total_pieces_completed,
            'defect_rate' => (float) $this->defect_rate,

            // Relationships
            'costs' => SeamstressCostResource::collection($this->whenLoaded('costs')),
            'active_costs' => SeamstressCostResource::collection($this->whenLoaded('activeCosts')),
            'assignments' => SeamstressAssignmentResource::collection($this->whenLoaded('assignments')),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
