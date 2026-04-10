<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeamstressDistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'cut'         => $this->when($this->relationLoaded('cut') && $this->cut, [
                'id'         => $this->cut?->id,
                'cut_number' => $this->cut?->cut_number,
            ]),
            'created_by'  => $this->when($this->relationLoaded('creator') && $this->creator, [
                'id'   => $this->creator?->id,
                'name' => trim(($this->creator?->name ?? '') . ' ' . ($this->creator?->last_name ?? '')),
            ]),
            'notes'       => $this->notes,
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'assignments' => SeamstressAssignmentResource::collection($this->whenLoaded('assignments')),
            'total_assignments'     => $this->whenLoaded('assignments', fn() => $this->assignments->count()),
            'total_pieces_assigned' => $this->whenLoaded('assignments', fn() => $this->assignments->sum('quantity_assigned')),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
