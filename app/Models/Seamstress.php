<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Seamstress extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'seamstresses';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'price_per_piece',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'price_per_piece' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function costs()
    {
        return $this->hasMany(SeamstressCost::class);
    }

    public function activeCosts()
    {
        return $this->hasMany(SeamstressCost::class)->where('is_active', true);
    }

    public function assignments()
    {
        return $this->hasMany(SeamstressAssignment::class);
    }

    // Accessors
    public function getTotalExtraCostsPerPieceAttribute(): float
    {
        return (float) $this->activeCosts
            ->where('cost_type', 'per_piece')
            ->sum('price');
    }

    public function getTotalFixedCostsAttribute(): float
    {
        return (float) $this->activeCosts
            ->where('cost_type', 'fixed')
            ->sum('price');
    }

    public function getFullCostPerPieceAttribute(): float
    {
        return (float) $this->price_per_piece + $this->total_extra_costs_per_piece;
    }

    public function getTotalPiecesCompletedAttribute(): int
    {
        return (int) $this->assignments->sum('quantity_returned');
    }

    public function getDefectRateAttribute(): float
    {
        $returned = $this->assignments->sum('quantity_returned');
        $defective = $this->assignments->sum('quantity_defective');
        if ($returned == 0) return 0;
        return round(($defective / $returned) * 100, 2);
    }
}
