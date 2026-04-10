<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SeamstressAssignment extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'distribution_id',
        'seamstress_id',
        'cut_production_id',
        'quantity_assigned',
        'quantity_returned',
        'quantity_defective',
        'price_per_piece',
        'status',
        'assigned_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'quantity_assigned' => 'integer',
        'quantity_returned' => 'integer',
        'quantity_defective' => 'integer',
        'price_per_piece' => 'decimal:2',
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    // Relationships
    public function seamstress()
    {
        return $this->belongsTo(Seamstress::class);
    }

    public function cutProduction()
    {
        return $this->belongsTo(CutProduction::class);
    }

    // Accessors
    public function getDefectRateAttribute(): float
    {
        if ($this->quantity_returned == 0) return 0;
        return round(($this->quantity_defective / $this->quantity_returned) * 100, 2);
    }

    public function getGoodPiecesAttribute(): int
    {
        return $this->quantity_returned - $this->quantity_defective;
    }

    public function getPendingPiecesAttribute(): int
    {
        return $this->quantity_assigned - $this->quantity_returned;
    }

    public function getSewingCostAttribute(): float
    {
        return round($this->good_pieces * (float) $this->price_per_piece, 2);
    }

    public function getExtraCostsAttribute(): float
    {
        $seamstress = $this->seamstress;
        if (!$seamstress) return 0;

        $perPieceCosts = $seamstress->total_extra_costs_per_piece * $this->good_pieces;
        $fixedCosts = $seamstress->total_fixed_costs;

        return round($perPieceCosts + $fixedCosts, 2);
    }

    public function getTotalSewingCostAttribute(): float
    {
        return round($this->sewing_cost + $this->extra_costs, 2);
    }

    public function getTotalPieceCostAttribute(): float
    {
        $production = $this->cutProduction;
        if (!$production || $this->good_pieces == 0) return 0;

        $fabricCostPerPiece = $production->fabric_cost_per_piece;
        $cuttingCostPerPiece = $production->cutting_cost_per_piece;
        $sewingCostPerPiece = $this->total_sewing_cost / $this->good_pieces;

        return round($fabricCostPerPiece + $cuttingCostPerPiece + $sewingCostPerPiece, 2);
    }
}
