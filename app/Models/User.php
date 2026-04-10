<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order;
use App\Models\UserAddress;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Role;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'cpf',
        'phone',
        'address',
        'number',
        'neighborhood',
        'complement',
        'city',
        'state',
        'zip_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function isSuperAdmin(): bool
    {
        $config = StoreConfiguration::current();
        $superAdmins = $config->super_admin_emails ?? config('acl.super_admins', []);
        return in_array($this->email, $superAdmins);
    }

        public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress(): ?UserAddress
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    // ─── Multi-tenancy ────────────────────────────────────────────────────────

    /**
     * Todos os tenants onde este usuário é staff (owner/manager/employee).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->using(TenantUser::class)
            ->withPivot(['role_id', 'is_active', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Registros de vínculo deste usuário com tenants.
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * Retorna o vínculo deste usuário com um tenant específico, ou null.
     */
    public function tenantUserFor(Tenant $tenant): ?TenantUser
    {
        return $this->tenantUsers()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('role')
            ->first();
    }

    /**
     * Verifica se o usuário é super admin da plataforma.
     * Mantém compatibilidade com o mecanismo atual (email no config/env).
     */
    public function isPlatformSuperAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Verifica se o usuário é dono de um tenant específico.
     */
    public function isOwnerOf(Tenant $tenant): bool
    {
        return $this->tenantUsers()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('name', Role::TENANT_OWNER))
            ->exists();
    }

    /**
     * Verifica se o usuário é staff (owner/manager/employee) de um tenant.
     */
    public function isStaffOf(Tenant $tenant): bool
    {
        return $this->tenantUsers()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Verifica se o usuário é um cliente (sem vínculo de staff com o tenant).
     */
    public function isCustomerAt(Tenant $tenant): bool
    {
        return ! $this->isStaffOf($tenant) && ! $this->isPlatformSuperAdmin();
    }
}