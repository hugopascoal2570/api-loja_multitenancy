<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantUserController extends Controller
{
    /**
     * GET /api/superadmin/tenants/{tenant}/users
     * Lista todos os membros staff do tenant.
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $members = TenantUser::with(['user', 'role', 'invitedBy'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get();

        return response()->json($members);
    }

    /**
     * POST /api/superadmin/tenants/{tenant}/users
     * Adiciona um usuário como staff do tenant.
     *
     * Se o usuário com o e-mail informado não existir, ele será criado com
     * senha aleatória (fluxo de convite — o usuário deverá usar "esqueci a senha").
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validRoles = [Role::TENANT_OWNER, Role::TENANT_MANAGER, Role::TENANT_EMPLOYEE];

        $data = $request->validate([
            'email' => 'required|email',
            'role'  => ['required', 'string', Rule::in($validRoles)],
            'name'  => 'sometimes|string|max:191', // usado só na criação de novo usuário
        ]);

        // Busca ou cria o usuário
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            if (empty($data['name'])) {
                return response()->json([
                    'message' => 'Usuário não encontrado. Informe o campo "name" para criá-lo.',
                ], 422);
            }

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt(\Illuminate\Support\Str::random(24)),
            ]);
        }

        // Verifica se já é membro ativo
        $existing = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return response()->json(['message' => 'Usuário já é membro deste tenant.'], 422);
            }

            // Reativa com novo papel
            $role = Role::where('name', $data['role'])->firstOrFail();
            $existing->update([
                'role_id'   => $role->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            return response()->json($existing->load(['user', 'role']));
        }

        $role = Role::where('name', $data['role'])->firstOrFail();

        $tenantUser = TenantUser::create([
            'tenant_id'   => $tenant->id,
            'user_id'     => $user->id,
            'role_id'     => $role->id,
            'is_active'   => true,
            'invited_by'  => $request->user()->id,
            'invited_at'  => now(),
            'joined_at'   => now(),
        ]);

        return response()->json($tenantUser->load(['user', 'role']), 201);
    }

    /**
     * PUT /api/superadmin/tenants/{tenant}/users/{user}
     * Altera o papel de um membro do tenant.
     */
    public function update(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $validRoles = [Role::TENANT_OWNER, Role::TENANT_MANAGER, Role::TENANT_EMPLOYEE];

        $data = $request->validate([
            'role'      => ['required', 'string', Rule::in($validRoles)],
            'is_active' => 'sometimes|boolean',
        ]);

        $tenantUser = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $role = Role::where('name', $data['role'])->firstOrFail();

        $tenantUser->update(array_filter([
            'role_id'   => $role->id,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json($tenantUser->load(['user', 'role']));
    }

    /**
     * DELETE /api/superadmin/tenants/{tenant}/users/{user}
     * Remove (desativa) um membro do tenant.
     */
    public function destroy(Tenant $tenant, User $user): JsonResponse
    {
        $tenantUser = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $tenantUser->update(['is_active' => false]);

        return response()->json(['message' => 'Usuário removido do tenant com sucesso.']);
    }
}
