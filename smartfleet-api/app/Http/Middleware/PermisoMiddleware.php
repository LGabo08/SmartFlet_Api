<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PermisoMiddleware
{
    public function handle(Request $request, Closure $next, string ...$claves): mixed
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        foreach ($claves as $clave) {
            if ($this->tienePermiso($user, $clave)) {
                return $next($request);
            }
        }

        return response()->json(['ok' => false, 'message' => 'No autorizado'], 403);
    }

    private function tienePermiso($user, string $clave): bool
    {
        // Cache por usuario para no golpear la BD en cada request
        $cacheKey = "permisos_usuario_{$user->getKey()}";

        $permisos = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            return $this->calcularPermisos($user);
        });

        return in_array($clave, $permisos);
    }

    private function calcularPermisos($user): array
    {
        $roleId = $user->role_id;

        // Permisos base del rol
        $permisosRol = \DB::table('rol_permiso as rp')
            ->join('permisos as p', 'p.id', '=', 'rp.fk_permiso')
            ->where('rp.fk_rol', $roleId)
            ->pluck('p.clave')
            ->toArray();

        // Personalizaciones del usuario (GRANT / DENY)
        $personalizados = \DB::table('usuario_permiso as up')
            ->join('permisos as p', 'p.id', '=', 'up.fk_permiso')
            ->where('up.fk_usuario', $user->getKey())
            ->select('p.clave', 'up.tipo')
            ->get();

        foreach ($personalizados as $p) {
            if ($p->tipo === 'GRANT' && !in_array($p->clave, $permisosRol)) {
                $permisosRol[] = $p->clave;
            }
            if ($p->tipo === 'DENY') {
                $permisosRol = array_filter($permisosRol, fn($c) => $c !== $p->clave);
            }
        }

        return array_values($permisosRol);
    }
}