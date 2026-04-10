<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TokenFromQuery
{
    public function handle(Request $request, Closure $next)
    {
        // Garante que auth:sanctum retorne 401 em vez de redirecionar para 'login'
        $request->headers->set('Accept', 'application/json');

        if ($request->has('token') && !$request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
