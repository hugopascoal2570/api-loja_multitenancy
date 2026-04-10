<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'link'         => $this->link,
            'is_featured'  => $this->is_featured,
            'position'     => $this->position,
            'image_url'    => $this->image_url,
            'start_date'   => $this->start_date?->toIso8601String(),
            'end_date'     => $this->end_date?->toIso8601String(),
            'device_type'  => $this->device_type,
            'active'       => $this->active,
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
        ];
    }
}