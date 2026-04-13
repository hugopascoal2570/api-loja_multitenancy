<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    // ── Feature keys disponíveis na plataforma ────────────────────────────────
    const FEATURE_PRODUCTS        = 'products';
    const FEATURE_CATEGORIES      = 'categories';
    const FEATURE_ORDERS          = 'orders';
    const FEATURE_BANNERS         = 'banners';
    const FEATURE_COUPONS         = 'coupons';
    const FEATURE_SHIPPING        = 'shipping';
    const FEATURE_INVENTORY       = 'inventory';
    const FEATURE_PRODUCTION      = 'production';      // cortes, costureiras
    const FEATURE_MERCADOLIVRE    = 'mercadolivre';
    const FEATURE_NEWSLETTER      = 'newsletter';
    const FEATURE_COUNTER_SALES   = 'counter_sales';   // PDV
    const FEATURE_ANALYTICS       = 'analytics';
    const FEATURE_PERMISSIONS_ACL = 'permissions_acl'; // Gestão de permissões

    const ALL_FEATURES = [
        self::FEATURE_PRODUCTS,
        self::FEATURE_CATEGORIES,
        self::FEATURE_ORDERS,
        self::FEATURE_BANNERS,
        self::FEATURE_COUPONS,
        self::FEATURE_SHIPPING,
        self::FEATURE_INVENTORY,
        self::FEATURE_PRODUCTION,
        self::FEATURE_MERCADOLIVRE,
        self::FEATURE_NEWSLETTER,
        self::FEATURE_COUNTER_SALES,
        self::FEATURE_ANALYTICS,
        self::FEATURE_PERMISSIONS_ACL,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'is_active',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'decimal:2',
        'features'  => 'array',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
