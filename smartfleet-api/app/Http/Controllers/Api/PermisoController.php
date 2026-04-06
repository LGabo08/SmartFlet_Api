<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class PermisoController extends Controller
{
    // ── GET /admin/permisos/roles ─────────────────────────────────────────────
    // Devuelve todos los roles con sus permisos actuales
    public function roles()
    {
        $roles = DB::table('roles')->get();

        $permisos = DB::table('permisos')
            ->orderBy('modulo')
            ->orderBy('clave')
            ->get();

        $data = $roles->map(function ($rol) use ($permisos) {
            $permisosDelRol = DB::table('rol_permiso')
                ->where('fk_rol', $rol->id)
                ->pluck('fk_permiso')
                ->toArray();

            return [
                'id'       => $rol->id,
                'nombre'   => $rol->nombre,
                'permisos' => $permisosDelRol,
            ];
        });

        // Agrupar permisos por módulo
        $modulos = $permisos->groupBy('modulo')->map(function ($items, $modulo) {
            return [
                'modulo'   => $modulo,
                'permisos' => $items->values(),
            ];
        })->values();

        return response()->json([
            'ok'     => true,
            'roles'  => $data,
            'modulos'=> $modulos,
        ]);
    }

    // ── PUT /admin/permisos/roles/{rolId} ─────────────────────────────────────
    // Actualiza los permisos de un rol completo
    public function actualizarRol(Request $request, int $rolId)
    {
        $request->validate([
            'permisos'   => 'required|array',
            'permisos.*' => 'integer|exists:permisos,id',
        ]);

        DB::transaction(function () use ($request, $rolId) {
            // Borrar permisos actuales del rol
            DB::table('rol_permiso')->where('fk_rol', $rolId)->delete();

            // Insertar los nuevos
            $rows = array_map(fn($pid) => [
                'fk_rol'     => $rolId,
                'fk_permiso' => $pid,
            ], $request->permisos);

            if (!empty($rows)) {
                DB::table('rol_permiso')->insert($rows);
            }

            // Limpiar cache de todos los usuarios de este rol
            $this->limpiarCacheRol($rolId);
        });

        return response()->json(['ok' => true, 'message' => 'Permisos del rol actualizados']);
    }

    // ── GET /admin/permisos/usuarios/{usuarioId} ──────────────────────────────
    // Devuelve los permisos personalizados de un usuario
    public function permisosUsuario(int $usuarioId)
    {
        $usuario = DB::table('usuarios')->where('idUsuario', $usuarioId)->first();

        if (!$usuario) {
            return response()->json(['ok' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        // Permisos base del rol
        $permisosRol = DB::table('rol_permiso as rp')
            ->join('permisos as p', 'p.id', '=', 'rp.fk_permiso')
            ->where('rp.fk_rol', $usuario->role_id)
            ->pluck('p.id')
            ->toArray();

        // Personalizaciones del usuario
        $personalizados = DB::table('usuario_permiso as up')
            ->join('permisos as p', 'p.id', '=', 'up.fk_permiso')
            ->where('up.fk_usuario', $usuarioId)
            ->select('p.id', 'up.tipo')
            ->get()
            ->keyBy('id');

        // Todos los permisos agrupados por módulo
        $permisos = DB::table('permisos')->orderBy('modulo')->orderBy('clave')->get();

        $modulos = $permisos->groupBy('modulo')->map(function ($items) use ($permisosRol, $personalizados) {
            return [
                'modulo'   => $items->first()->modulo,
                'permisos' => $items->map(function ($p) use ($permisosRol, $personalizados) {
                    $enRol  = in_array($p->id, $permisosRol);
                    $custom = $personalizados->get($p->id);

                    // Estado final del permiso para este usuario
                    $activo = $enRol;
                    $tipo   = null;

                    if ($custom) {
                        $activo = $custom->tipo === 'GRANT';
                        $tipo   = $custom->tipo;
                    }

                    return [
                        'id'          => $p->id,
                        'clave'       => $p->clave,
                        'descripcion' => $p->descripcion,
                        'en_rol'      => $enRol,
                        'tipo'        => $tipo,   // GRANT, DENY o null
                        'activo'      => $activo,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'ok'      => true,
            'usuario' => $usuario,
            'modulos' => $modulos,
        ]);
    }

    // ── PUT /admin/permisos/usuarios/{usuarioId} ──────────────────────────────
    // Guarda personalizaciones de un usuario (GRANT / DENY)
    public function actualizarUsuario(Request $request, int $usuarioId)
    {
        $request->validate([
            'personalizaciones'        => 'required|array',
            'personalizaciones.*.id'   => 'required|integer|exists:permisos,id',
            'personalizaciones.*.tipo' => 'required|in:GRANT,DENY,NINGUNO',
        ]);

        DB::transaction(function () use ($request, $usuarioId) {
            // Borrar personalizaciones anteriores
            DB::table('usuario_permiso')->where('fk_usuario', $usuarioId)->delete();

            // Insertar solo las que no son NINGUNO
            $rows = collect($request->personalizaciones)
                ->filter(fn($p) => $p['tipo'] !== 'NINGUNO')
                ->map(fn($p) => [
                    'fk_usuario' => $usuarioId,
                    'fk_permiso' => $p['id'],
                    'tipo'       => $p['tipo'],
                    'created_at' => now(),
                ])
                ->values()
                ->toArray();

            if (!empty($rows)) {
                DB::table('usuario_permiso')->insert($rows);
            }

            // Limpiar cache del usuario
            Cache::forget("permisos_usuario_{$usuarioId}");
        });

        return response()->json(['ok' => true, 'message' => 'Permisos del usuario actualizados']);
    }

    // ── POST /admin/permisos/sincronizar ──────────────────────────────────────
    // Ejecuta el comando artisan desde el botón en el admin
    public function sincronizar()
    {
        Artisan::call('permisos:sincronizar');
        $output = Artisan::output();

        return response()->json([
            'ok'     => true,
            'output' => $output,
        ]);
    }

    // ── PRIVADO: limpiar cache de todos los usuarios de un rol ────────────────
    private function limpiarCacheRol(int $rolId): void
    {
        $usuarios = DB::table('usuarios')->where('role_id', $rolId)->pluck('idUsuario');
        foreach ($usuarios as $id) {
            Cache::forget("permisos_usuario_{$id}");
        }
    }
}