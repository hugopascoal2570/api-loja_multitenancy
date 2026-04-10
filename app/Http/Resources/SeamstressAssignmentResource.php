<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeamstressAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seamstress_id' => $this->seamstress_id,
            'cut_production_id' => $this->cut_production_id,
            'quantity_assigned' => $this->quantity_assigned,
            'quantity_returned' => $this->quantity_returned,
            'quantity_defective' => $this->quantity_defective,
            'price_per_piece' => (float) $this->price_per_piece,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'returned_at' => $this->returned_at?->toDateTimeString(),
            'notes' => $this->notes,

            // Calculated fields
            'defect_rate' => (float) $this->defect_rate,
            'good_pieces' => $this->good_pieces,
            'pending_pieces' => $this->pending_pieces,
            'sewing_cost' => (float) $this->sewing_cost,
            'extra_costs' => (float) $this->extra_costs,
            'total_sewing_cost' => (float) $this->total_sewing_cost,
            'total_piece_cost' => (float) $this->total_piece_cost,

            // Relationships (dados básicos para evitar referência circular)
            'seamstress' => $this->when($this->relationLoaded('seamstress') && $this->seamstress, [
                'id'              => $this->seamstress?->id,
                'name'            => $this->seamstress?->name,
                'phone'           => $this->seamstress?->phone,
                'price_per_piece' => (float) $this->seamstress?->price_per_piece,
                'full_cost_per_piece' => (float) $this->seamstress?->full_cost_per_piece,
            ]),
            'cut_production' => $this->when($this->relationLoaded('cutProduction') && $this->cutProduction, [
                'id'                  => $this->cutProduction?->id,
                'cut_id'              => $this->cutProduction?->cut_id,
                'product_description' => $this->cutProduction?->product_description,
                'product_display_name'=> $this->cutProduction?->product_display_name,
                'quantity_produced'   => $this->cutProduction?->quantity_produced,
                'quantity_assigned'   => $this->cutProduction?->quantity_assigned,
                'quantity_available'  => $this->cutProduction?->quantity_available,
            ]),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
            'assigned'    => 'Distribuído',
            'in_progress' => 'Em Andamento',
            'returned'    => 'Devolvido',
            default       => 'Desconhecido',
        };
    }
}
