<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name','slug','reference', 'description',
        'retail_price', 'wholesale_price', 'wholesale_min_qty',
        'category_id', 'is_highlighted', 'is_promotion', 'promotion_price', 'ml_price',
        'promotion_percent', 'is_new', 'is_new_collection','active',
        'weight', 'width', 'height', 'length',
        'measurement_image'
    ];
    

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderByRaw("
            FIELD(size, 'PP', 'P', 'M', 'G', 'GG', 'EGG', 'XG', 'XGG', 'ÚNICO')
        ");
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('position')->orderByDesc('is_main');
    }

    public function category()
    {
    return $this->belongsTo(Category::class);
    }

    public function kits()
    {
    return $this->hasMany(ProductKit::class);
    }

    public function measurements()
    {
        return $this->hasMany(ProductMeasurement::class)->orderBy('sort_order');
    }

}
