<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasUuids;

    // ─── Papéis da plataforma (guard = platform) ──────────────────────────────
    const PLATFORM_SUPER_ADMIN = 'platform_super_admin';

    // ─── Papéis de tenant (guard = tenant) ───────────────────────────────────
    const TENANT_OWNER    = 'tenant_owner';
    const TENANT_MANAGER  = 'tenant_manager';
    const TENANT_EMPLOYEE = 'tenant_employee';

    protected $fillable = [
        'name',
        'display_name',
        'guard',
        'description',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function tenantUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPlatformRole(): bool
    {
        return $this->guard === 'platform';
    }

    public function isTenantRole(): bool
    {
        return $this->guard === 'tenant';
    }
}
