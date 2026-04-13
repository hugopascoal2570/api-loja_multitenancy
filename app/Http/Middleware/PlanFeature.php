<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o plano do tenant atual permite acesso à feature solicitada.
 *
 * Uso nas rotas:
 *   ->middleware('plan.feature:mercadolivre')
 *   ->middleware('plan.feature:production')
 *
 * Super admins da plataforma sempre passam sem restrição de plano.
 */
class PlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        // Super admin da plataforma: sem restrição de plano
        if ($user && $user->isPlatformSuperAdmin()) {
            return $next($request);
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'message'    => 'Tenant não identificado.',
                'error_code' => 'TENANT_NOT_FOUND',
            ], 404);
        }

        if (! $tenant->hasFeature($feature)) {
            return response()->json([
                'message'    => "O plano desta loja não inclui acesso a este recurso ({$feature}).",
                'error_code' => 'PLAN_FEATURE_RESTRICTED',
                'feature'    => $feature,
            ], 403);
        }

        return $next($request);
    }
}
