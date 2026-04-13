<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que a requisição pertença a um super admin da plataforma.
 *
 * Verificação dupla:
 *  1. O token Sanctum deve ter a ability "context:platform_super_admin"
 *     (emitida no login quando isPlatformSuperAdmin() == true).
 *  2. O usuário autenticado confirma isPlatformSuperAdmin() no banco
 *     (defesa contra tokens forjados ou usuários rebaixados após o login).
 *
 * Uso:
 *   Route::middleware(['auth:sanctum', 'platform.superadmin'])->group(...)
 */
class PlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        // Verifica ability no token Sanctum
        $tokenHasAbility = $user->currentAccessToken()
            && $user->currentAccessToken()->can('context:platform_super_admin');

        // Verifica no banco (defesa em profundidade)
        $userIsSuperAdmin = $user->isPlatformSuperAdmin();

        if (! $tokenHasAbility || ! $userIsSuperAdmin) {
            return response()->json([
                'message'    => 'Acesso restrito ao super administrador da plataforma.',
                'error_code' => 'PLATFORM_SUPER_ADMIN_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
