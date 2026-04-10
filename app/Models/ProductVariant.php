<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'size',
        'color',
        'stock',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id');
    }

    public function kitItems()
    {
        return $this->hasMany(ProductKitItem::class, 'variant_id');
    }

    public function originalKitItems()
    {
        return $this->hasMany(ProductKitItemOriginal::class, 'variant_id');
    }


    public function getImageUrlAttribute()
    {
        if ($this->product && $this->product->images->isNotEmpty()) {
            return $this->product->images->first()->url ?? null;
        }

        return null;
    }

    /**
     * Retorna a URL da imagem desta variante específica
     * Se não houver, faz fallback para a imagem principal do produto
     */
    public function getVariantImageUrl(): ?string
    {
        // 1. Tenta pegar imagem principal desta variante
        $variantImage = $this->images()
            ->where('is_main', true)
            ->first();

        if ($variantImage) {
            return $variantImage->url;
        }

        // 2. Tenta pegar qualquer imagem desta variante
        $anyVariantImage = $this->images()->first();
        if ($anyVariantImage) {
            return $anyVariantImage->url;
        }

        // 3. Fallback: Imagem principal do produto
        $productMainImage = $this->product?->images()
            ->where('is_main', true)
            ->whereNull('variant_id') // Apenas imagens gerais do produto
            ->first();

        if ($productMainImage) {
            return $productMainImage->url;
        }

        // 4. Fallback final: Qualquer imagem do produto
        $anyProductImage = $this->product?->images()
            ->whereNull('variant_id')
            ->first();

        return $anyProductImage?->url;
    }
}
