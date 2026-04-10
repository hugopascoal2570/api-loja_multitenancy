<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends Model
{
    use HasUuids, SoftDeletes;
    protected $fillable = [
        'product_variant_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'related_order_id',
        'notes',
        'user_id',
        'reversal_of_id',
        'reversed_by',
        'reversed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    // Apenas movimentos manuais podem ser revertidos
    private const REVERTIBLE_REASONS = ['manual_add', 'manual_remove', 'manual_set'];

    public function canBeReverted(): bool
    {
        return in_array($this->reason, self::REVERTIBLE_REASONS)
            && is_null($this->reversed_at)
            && is_null($this->reversal_of_id); // reversões não podem ser revertidas
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'reversal_of_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
