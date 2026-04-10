<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantUser extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'tenant_users';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'is_active',
        'invited_by',
        'invited_at',
        'joined_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'invited_at'  => 'datetime',
        'joined_at'   => 'datetime',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role->name === Role::TENANT_OWNER;
    }

    public function isManager(): bool
    {
        return $this->role->name === Role::TENANT_MANAGER;
    }

    public function isStaff(): bool
    {
        return in_array($this->role->name, [
            Role::TENANT_OWNER,
            Role::TENANT_MANAGER,
            Role::TENANT_EMPLOYEE,
        ]);
    }

    /**
     * Verifica se este usuário tem uma permissão específica neste tenant,
     * considerando: override individual > permissões do role.
     */
    public function hasPermission(string $permissionName): bool
    {
        // 1. Verifica override individual (granted ou revogado)
        $override = TenantUserPermission::where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->user_id)
            ->whereHas('permission', fn ($q) => $q->where('name', $permissionName))
            ->first();

        if ($override !== null) {
            return (bool) $override->granted;
        }

        // 2. Verifica permissões do role
        return $this->role->permissions()->where('name', $permissionName)->exists();
    }
}
