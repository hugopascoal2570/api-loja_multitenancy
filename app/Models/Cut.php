<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Cut extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'cut_number',
        'cutting_labor_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'cutting_labor_cost' => 'decimal:2',
    ];

    // Relationships
    public function fabricRolls()
    {
        return $this->hasMany(FabricRoll::class);
    }

    public function productions()
    {
        return $this->hasMany(CutProduction::class);
    }

    // Accessors
    public function getTotalFabricCostAttribute(): float
    {
        return (float) $this->fabricRolls->sum(fn($roll) => $roll->total_price);
    }

    public function getTotalRollsAttribute(): int
    {
        return (int) $this->fabricRolls->sum('quantity_rolls');
    }

    public function getTotalMetersAttribute(): float
    {
        return (float) $this->fabricRolls->sum('meters');
    }

    public function getTotalPiecesProducedAttribute(): int
    {
        return (int) $this->productions->sum('quantity_produced');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->total_fabric_cost + (float) $this->cutting_labor_cost;
    }

    public function getCostPerPieceFromCutAttribute(): float
    {
        $totalPieces = $this->total_pieces_produced;
        if ($totalPieces === 0) return 0;
        return round($this->total_cost / $totalPieces, 2);
    }

    // Auto-generate sequential number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cut) {
            if (!$cut->cut_number) {
                $cut->cut_number = (static::withTrashed()->max('cut_number') ?? 0) + 1;
            }
        });
    }
}
