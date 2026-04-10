<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FabricRoll extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'cut_id',
        'color',
        'quantity_rolls',
        'meters',
        'price_per_roll',
        'price_per_meter',
        'notes',
    ];

    protected $casts = [
        'quantity_rolls' => 'integer',
        'meters' => 'decimal:2',
        'price_per_roll' => 'decimal:2',
        'price_per_meter' => 'decimal:2',
    ];

    // Relationships
    public function cut()
    {
        return $this->belongsTo(Cut::class);
    }

    public function productions()
    {
        return $this->hasMany(CutProduction::class);
    }

    // Accessors
    public function getCostPerMeterAttribute(): float
    {
        // Se tiver price_per_meter definido, usa ele
        if ($this->price_per_meter > 0) {
            return (float) $this->price_per_meter;
        }
        // Senao calcula pelo preco total / metros
        if ($this->meters == 0) return 0;
        return round((float) $this->price_per_roll / (float) $this->meters, 2);
    }

    public function getTotalPriceAttribute(): float
    {
        // Se tiver price_per_roll definido, usa ele
        if ($this->price_per_roll > 0) {
            return (float) $this->price_per_roll;
        }
        // Senao calcula pelo preco por metro * metros
        return round((float) $this->price_per_meter * (float) $this->meters, 2);
    }

    public function getAverageMetersPerRollAttribute(): float
    {
        if ($this->quantity_rolls == 0) return 0;
        return round((float) $this->meters / $this->quantity_rolls, 2);
    }

    public function getMetersUsedAttribute(): float
    {
        // Se a relação já foi carregada, usa ela (sem query adicional)
        if ($this->relationLoaded('productions')) {
            return (float) ($this->productions->sum('fabric_meters_used') ?? 0);
        }

        // Caso contrário, faz query direta no banco (mais eficiente que lazy loading)
        return (float) CutProduction::where('fabric_roll_id', $this->id)
            ->sum('fabric_meters_used');
    }

    public function getMetersRemainingAttribute(): float
    {
        return (float) $this->meters - $this->meters_used;
    }
}
