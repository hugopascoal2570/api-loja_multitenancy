<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size' => $this->size,
            'bust' => $this->bust ? (float) $this->bust : null,
            'waist' => $this->waist ? (float) $this->waist : null,
            'hip' => $this->hip ? (float) $this->hip : null,
            'waistband' => $this->waistband ? (float) $this->waistband : null,
            'rise' => $this->rise ? (float) $this->rise : null,
            'inseam' => $this->inseam ? (float) $this->inseam : null,
            'thigh' => $this->thigh ? (float) $this->thigh : null,
            'length' => $this->length ? (float) $this->length : null,
            'shoulder' => $this->shoulder ? (float) $this->shoulder : null,
            'sleeve' => $this->sleeve ? (float) $this->sleeve : null,
        ];
    }
}
