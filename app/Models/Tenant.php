<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'plan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function primaryDomain(): ?TenantDomain
    {
        return $this->domains()->where('is_primary', true)->first();
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * Todos os usuários vinculados a este tenant (staff: owners, managers, employees).
     * Clientes NÃO aparecem aqui.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->using(TenantUser::class)
            ->withPivot(['role_id', 'is_active', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasUser(User $user): bool
    {
        return $this->tenantUsers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function getUrlAttribute(): string
    {
        $primary = $this->primaryDomain();
        return $primary ? "https://{$primary->domain}" : "https://{$this->slug}." . config('app.base_domain', 'vendafacil.com.br');
    }
}
