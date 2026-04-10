<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CutProduction extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'cut_id',
        'fabric_roll_id',
        'product_id',
        'product_variant_id',
        'product_description',
        'quantity_produced',
        'fabric_meters_used',
        'notes',
    ];

    protected $casts = [
        'quantity_produced' => 'integer',
        'fabric_meters_used' => 'decimal:2',
    ];

    // Relationships
    public function cut()
    {
        return $this->belongsTo(Cut::class);
    }

    public function fabricRoll()
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function assignments()
    {
        return $this->hasMany(SeamstressAssignment::class);
    }

    // Accessors
    public function getFabricCostAttribute(): float
    {
        if (!$this->fabricRoll || !$this->fabric_meters_used) return 0;
        return round($this->fabricRoll->cost_per_meter * (float) $this->fabric_meters_used, 2);
    }

    public function getFabricCostPerPieceAttribute(): float
    {
        if ($this->quantity_produced == 0) return 0;
        return round($this->fabric_cost / $this->quantity_produced, 2);
    }

    public function getCuttingCostPerPieceAttribute(): float
    {
        $cut = $this->cut;
        if (!$cut || $cut->total_pieces_produced == 0) return 0;
        return round((float) $cut->cutting_labor_cost / $cut->total_pieces_produced, 2);
    }

    public function getQuantityAssignedAttribute(): int
    {
        return (int) $this->assignments->sum('quantity_assigned');
    }

    public function getQuantityAvailableAttribute(): int
    {
        return $this->quantity_produced - $this->quantity_assigned;
    }

    public function getProductDisplayNameAttribute(): string
    {
        if ($this->product) {
            $name = $this->product->name;
            if ($this->productVariant) {
                $name .= " - {$this->productVariant->color}/{$this->productVariant->size}";
            }
            return $name;
        }
        return $this->product_description ?? 'Produto a definir';
    }
}
