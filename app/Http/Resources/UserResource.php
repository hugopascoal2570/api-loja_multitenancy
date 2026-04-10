<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'cpf'   => $this->cpf   ? preg_replace('/(\d{3})\.\d{3}\.\d{3}-(\d{2})/', '$1.***.***-$2', $this->cpf) : null,
            'phone' => $this->phone ? preg_replace('/(\d{2})\d{5}(\d{4})/', '$1*****$2', preg_replace('/\D/', '', $this->phone)) : null,

            'address' => $this->address,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'complement' => $this->complement,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,

            'is_admin'   => (bool) $this->is_admin,
            'created_at'           => $this->created_at?->toISOString(),
            'created_at_formatted' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}