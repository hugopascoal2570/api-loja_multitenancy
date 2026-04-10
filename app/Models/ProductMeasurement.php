<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMeasurement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'size',
        'bust',
        'waist',
        'hip',
        'waistband',
        'rise',
        'inseam',
        'thigh',
        'length',
        'shoulder',
        'sleeve',
        'sort_order',
    ];

    protected $casts = [
        'bust' => 'decimal:1',
        'waist' => 'decimal:1',
        'hip' => 'decimal:1',
        'waistband' => 'decimal:1',
        'rise' => 'decimal:1',
        'inseam' => 'decimal:1',
        'thigh' => 'decimal:1',
        'length' => 'decimal:1',
        'shoulder' => 'decimal:1',
        'sleeve' => 'decimal:1',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
