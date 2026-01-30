<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        // Usa la relación Usuario->role() que ya tienes
        $roleName = optional($user->role)->nombre;

        if (!$roleName || !in_array($roleName, $roles)) {
            return response()->json(['ok' => false, 'message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
