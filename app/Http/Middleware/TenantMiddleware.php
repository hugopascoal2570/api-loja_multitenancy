<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Resolve o tenant a partir do subdomínio da requisição e disponibiliza
     * no container da aplicação via app('tenant').
     *
     * Fluxo:
     *   lojax.vendafacil.com.br → busca TenantDomain::where('domain', host) → seta app('tenant')
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $tenantDomain = TenantDomain::with('tenant')
            ->where('domain', $host)
            ->first();

        if (! $tenantDomain) {
            return response()->json([
                'message' => 'Loja não encontrada.',
                'error_code' => 'TENANT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $tenant = $tenantDomain->tenant;

        if (! $tenant->is_active) {
            return response()->json([
                'message' => 'Esta loja está temporariamente inativa.',
                'error_code' => 'TENANT_INACTIVE',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Disponibiliza o tenant no container para uso em toda a requisição
        app()->instance('tenant', $tenant);

        // Injeta no request para facilitar acesso nos controllers
        $request->merge(['_tenant' => $tenant]);

        return $next($request);
    }
}
