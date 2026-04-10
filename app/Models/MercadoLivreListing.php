<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLivreListing extends Model
{
    protected $fillable = [
        'product_id',
        'ml_item_id',
        'status',
        'ml_category_id',
        'ml_piece_chart_id',
        'synced_at',
        'last_error',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
