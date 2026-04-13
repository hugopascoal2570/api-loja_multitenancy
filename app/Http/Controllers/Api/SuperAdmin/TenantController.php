<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * GET /api/superadmin/tenants
     * Lista todos os tenants com seus domínios e plano.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::with(['plan', 'domains'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return response()->json($tenants);
    }

    /**
     * POST /api/superadmin/tenants
     * Cria um novo tenant com seu domínio primário.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:191',
            'slug'    => 'required|string|max:100|alpha_dash|unique:tenants,slug',
            'domain'  => 'nullable|string|max:253',   // domínio personalizado; se omitido, usa slug.base_domain
            'plan_id' => 'nullable|uuid|exists:plans,id',
            'is_active' => 'boolean',
        ]);

        $tenant = Tenant::create([
            'name'      => $data['name'],
            'slug'      => $data['slug'],
            'plan_id'   => $data['plan_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Domínio primário
        $domain = $data['domain'] ?? "{$data['slug']}." . config('app.base_domain', 'vendafacil.com.br');

        TenantDomain::create([
            'tenant_id'  => $tenant->id,
            'domain'     => $domain,
            'is_primary' => true,
        ]);

        return response()->json(
            $tenant->load(['plan', 'domains']),
            201
        );
    }

    /**
     * GET /api/superadmin/tenants/{tenant}
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json($tenant->load(['plan', 'domains', 'tenantUsers.user', 'tenantUsers.role']));
    }

    /**
     * PUT /api/superadmin/tenants/{tenant}
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:191',
            'slug'      => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'plan_id'   => 'nullable|uuid|exists:plans,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $tenant->update($data);

        return response()->json($tenant->load(['plan', 'domains']));
    }

    /**
     * DELETE /api/superadmin/tenants/{tenant}
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();
        return response()->json(null, 204);
    }

    // ── Activate / Deactivate ─────────────────────────────────────────────────

    /**
     * POST /api/superadmin/tenants/{tenant}/activate
     */
    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => true]);
        return response()->json(['message' => 'Tenant ativado com sucesso.', 'tenant' => $tenant]);
    }

    /**
     * POST /api/superadmin/tenants/{tenant}/deactivate
     */
    public function deactivate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => false]);
        return response()->json(['message' => 'Tenant desativado com sucesso.', 'tenant' => $tenant]);
    }

    // ── Domains ───────────────────────────────────────────────────────────────

    /**
     * POST /api/superadmin/tenants/{tenant}/domains
     */
    public function addDomain(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'domain'     => 'required|string|max:253|unique:tenant_domains,domain',
            'is_primary' => 'boolean',
        ]);

        if (! empty($data['is_primary'])) {
            // Remove primário dos outros domínios deste tenant
            $tenant->domains()->update(['is_primary' => false]);
        }

        $domain = $tenant->domains()->create([
            'domain'     => $data['domain'],
            'is_primary' => $data['is_primary'] ?? false,
        ]);

        return response()->json($domain, 201);
    }

    /**
     * DELETE /api/superadmin/tenants/{tenant}/domains/{domain}
     */
    public function removeDomain(Tenant $tenant, TenantDomain $domain): JsonResponse
    {
        abort_if($domain->tenant_id !== $tenant->id, 404);
        abort_if($domain->is_primary && $tenant->domains()->count() > 1, 422, 'Não é possível remover o domínio primário enquanto há outros domínios. Defina outro como primário primeiro.');

        $domain->delete();
        return response()->json(null, 204);
    }

    /**
     * PUT /api/superadmin/tenants/{tenant}/domains/{domain}/set-primary
     */
    public function setPrimaryDomain(Tenant $tenant, TenantDomain $domain): JsonResponse
    {
        abort_if($domain->tenant_id !== $tenant->id, 404);

        $tenant->domains()->update(['is_primary' => false]);
        $domain->update(['is_primary' => true]);

        return response()->json(['message' => 'Domínio primário atualizado.', 'domain' => $domain]);
    }
}
