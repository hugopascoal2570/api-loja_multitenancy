<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductKit extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'total_quantity',
        'fixed_size',
        'fixed_color',
        'price',
        'is_featured',
        'is_redistributed',
        'is_active',
        'redistributed_at',
        'weight',
        'width',
        'height',
        'length',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_redistributed' => 'boolean',
        'is_active' => 'boolean',
        'redistributed_at' => 'datetime',
    ];
    

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(ProductKitItem::class);
    }

    public function originalItems()
    {
        return $this->hasMany(ProductKitItemOriginal::class, 'product_kit_id');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Retorna o link do produto com este kit selecionado
     * Ex: /produto/calca-pantalona?kit=uuid-do-kit
     */
    public function getProductLinkAttribute(): string
    {
        return "/produto/{$this->product->slug}?kit={$this->id}";
    }

    /**
     * Retorna a imagem principal do produto para exibir o kit
     */
    public function getMainImageAttribute(): ?string
    {
        $mainImage = $this->product?->images()
            ->where('is_main', true)
            ->first();

        if ($mainImage) {
            return $mainImage->url;
        }

        return $this->product?->images()->first()?->url;
    }
}
