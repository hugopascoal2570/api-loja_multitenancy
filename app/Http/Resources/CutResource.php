<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cut_number' => $this->cut_number,
            'cutting_labor_cost' => (float) $this->cutting_labor_cost,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'notes' => $this->notes,

            // Calculated fields
            'total_rolls' => $this->total_rolls,
            'total_meters' => (float) $this->total_meters,
            'total_fabric_cost' => (float) $this->total_fabric_cost,
            'total_cost' => (float) $this->total_cost,
            'total_pieces_produced' => $this->total_pieces_produced,
            'cost_per_piece_from_cut' => (float) $this->cost_per_piece_from_cut,

            // Relationships
            'fabric_rolls' => FabricRollResource::collection($this->whenLoaded('fabricRolls')),
            'productions' => CutProductionResource::collection($this->whenLoaded('productions')),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pendente',
            'in_progress' => 'Em Andamento',
            'completed' => 'Concluido',
            default => $this->status,
        };
    }
}
