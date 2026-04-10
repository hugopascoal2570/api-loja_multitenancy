<?php

namespace App\Http\Middleware;

use App\Models\StoreConfiguration;
use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $config = StoreConfiguration::current();
        $superAdmins = $config->super_admin_emails ?? config('acl.super_admins', []);

        if (!in_array($request->user()?->email, $superAdmins, strict: true)) {
            return response()->json(['message' => 'Acesso restrito ao super administrador.'], 403);
        }

        return $next($request);
    }
}
