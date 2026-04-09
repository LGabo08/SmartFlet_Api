<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ViajeController;
use App\Http\Controllers\Api\AgregarViajeController;
use App\Http\Controllers\Api\CertificacionController;
use App\Http\Controllers\Api\RutaController;
use App\Http\Controllers\Api\LicenciaController;
use App\Http\Controllers\Api\OperadorController;
use App\Http\Controllers\Api\UnidadController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\OperadorCuotaController;
use App\Http\Controllers\Api\ZonaController;
use App\Http\Controllers\Api\PanelController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\SincronizacionController;
// ── PÚBLICAS ──────────────────────────────────────────────────────────────────
Route::get('/ping', fn() => response()->json(['ok' => true, 'msg' => 'pong']));
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ── PROTEGIDAS ────────────────────────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {

    // ── AUTH ──────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::get('/me',       [AuthController::class, 'me']);
        Route::post('/logout',  [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ── PANEL ─────────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_panel')
        ->get('/panel/resumen', [PanelController::class, 'resumen']);

    // ── ZONAS ─────────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_zonas')->group(function () {
        Route::get('/zonas',      [ZonaController::class, 'index']);
        Route::get('/zonas/{id}', [ZonaController::class, 'show']);
    });
    Route::middleware('permiso:crear_zonas')
        ->post('/zonas', [ZonaController::class, 'store']);
    Route::middleware('permiso:editar_zonas')
        ->put('/zonas/{id}', [ZonaController::class, 'update']);
    Route::middleware('permiso:eliminar_zonas')
        ->delete('/zonas/{id}', [ZonaController::class, 'destroy']);

    // ── CLIENTES ──────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_clientes')->group(function () {
        Route::get('/clientes/selector', [ClienteController::class, 'listadoSelector']);
        Route::get('/clientes',          [ClienteController::class, 'index']);
    });

    // ── LICENCIAS ─────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_licencias')->group(function () {
        Route::get('/licencias',            [LicenciaController::class, 'index']);
        Route::get('/licencias/{licencia}', [LicenciaController::class, 'show']);
    });
    Route::middleware('permiso:crear_licencias')
        ->post('/licencias', [LicenciaController::class, 'store']);
    Route::middleware('permiso:editar_licencias')
        ->put('/licencias/{licencia}', [LicenciaController::class, 'update']);
    Route::middleware('permiso:eliminar_licencias')
        ->delete('/licencias/{licencia}', [LicenciaController::class, 'destroy']);

    // ── RUTAS ─────────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_rutas')->group(function () {
        Route::get('/rutas',        [RutaController::class, 'index']);
        Route::get('/rutas/{ruta}', [RutaController::class, 'show']);
    });
    Route::middleware('permiso:crear_rutas')
        ->post('/rutas', [RutaController::class, 'store']);
    Route::middleware('permiso:editar_rutas')
        ->put('/rutas/{ruta}', [RutaController::class, 'update']);
    Route::middleware('permiso:eliminar_rutas')
        ->delete('/rutas/{ruta}', [RutaController::class, 'destroy']);
// ── SINCRONIZACIÓN ORACLE ─────────────────────────────────────────────────
Route::get('/sincronizar/verificar', [SincronizacionController::class, 'verificar']);
Route::middleware('permiso:sincronizar_permisos')
    ->post('/sincronizar/manual', [SincronizacionController::class, 'sincronizarManual']);
    // ── CERTIFICACIONES ───────────────────────────────────────────────────────
    Route::middleware('permiso:ver_certificaciones')->group(function () {
        Route::get('/certificaciones',                     [CertificacionController::class, 'index']);
        Route::get('/certificaciones/{certificacion}',     [CertificacionController::class, 'show']);
        Route::get('/certificaciones/cliente/{clienteId}', [CertificacionController::class, 'getCertificacionesPorCliente']);
    });
    Route::middleware('permiso:crear_certificaciones')
        ->post('/certificaciones', [CertificacionController::class, 'store']);
    Route::middleware('permiso:editar_certificaciones')
        ->put('/certificaciones/{certificacion}', [CertificacionController::class, 'update']);
    Route::middleware('permiso:eliminar_certificaciones')
        ->delete('/certificaciones/{certificacion}', [CertificacionController::class, 'destroy']);

    // ── OPERADORES ────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_operadores')->group(function () {
        Route::get('/operadores',      [OperadorController::class, 'index']);
        // whereNumber impide que 'cuotas-global' sea capturado como {id}
        Route::get('/operadores/{id}', [OperadorController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permiso:ver_historial_operador')->group(function () {
        Route::get('operadores/{id}/historial-estado',      [OperadorController::class, 'historialEstado'])->whereNumber('id');
        Route::get('operadores/{id}/historial-zona',        [OperadorController::class, 'historialZona'])->whereNumber('id');
        Route::get('/operadores/{id_operador}/movimientos', [ViajeController::class,    'movimientosOperador'])->whereNumber('id_operador');
    });
    Route::middleware('permiso:crear_operadores')
        ->post('/operadores', [OperadorController::class, 'store']);
    Route::middleware('permiso:editar_operadores')
        ->put('/operadores/{id}', [OperadorController::class, 'update'])->whereNumber('id');
    Route::middleware('permiso:eliminar_operadores')
        ->delete('/operadores/{id}', [OperadorController::class, 'destroy'])->whereNumber('id');
    Route::middleware('permiso:cambiar_estado_operador')
        ->post('operadores/{id}/cambiar-estado', [OperadorController::class, 'cambiarEstado'])->whereNumber('id');
    Route::middleware('permiso:cambiar_zona_operador')
        ->post('operadores/{id}/cambiar-zona', [OperadorController::class, 'cambiarZona'])->whereNumber('id');

    // ── CUOTAS ────────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_cuotas')->group(function () {
        // cuotas-global antes de {idOperador}/cuotas (doble protección junto con whereNumber arriba)
        Route::get('/operadores/cuotas-global',       [OperadorCuotaController::class, 'todosConCuotas']);
        Route::get('/operadores/{idOperador}/cuotas', [OperadorCuotaController::class, 'porOperador'])->whereNumber('idOperador');
        Route::get('/operador-cuotas',                [OperadorCuotaController::class, 'index']);
        Route::get('/operador-cuotas/{id}',           [OperadorCuotaController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permiso:crear_cuotas')
        ->post('/operador-cuotas', [OperadorCuotaController::class, 'store']);
    Route::middleware('permiso:editar_cuotas')
        ->put('/operador-cuotas/{id}', [OperadorCuotaController::class, 'update'])->whereNumber('id');
    Route::middleware('permiso:eliminar_cuotas')
        ->delete('/operador-cuotas/{id}', [OperadorCuotaController::class, 'destroy'])->whereNumber('id');

    // ── UNIDADES ──────────────────────────────────────────────────────────────
    Route::middleware('permiso:ver_unidades')->group(function () {
        Route::get('/unidades',              [UnidadController::class, 'index']);
        Route::get('/unidades/{id}',         [UnidadController::class, 'show'])->whereNumber('id');
        Route::get('/unidades/{id}/detalle', [UnidadController::class, 'showConDetalle'])->whereNumber('id');
    });
    Route::middleware('permiso:ver_historial_unidad')->group(function () {
        Route::get('/unidades/{id}/historial-operadores',      [UnidadController::class, 'historialOperadores'])->whereNumber('id');
        Route::get('/unidades/{id}/historial-zona',            [UnidadController::class, 'historialZona'])->whereNumber('id');
        Route::get('/unidades/{id}/historial-estado-filtrado', [UnidadController::class, 'historialEstadoFiltrado'])->whereNumber('id');
    });
    Route::middleware('permiso:crear_unidades')
        ->post('/unidades', [UnidadController::class, 'store']);
    Route::middleware('permiso:editar_unidades')
        ->put('/unidades/{id}', [UnidadController::class, 'update'])->whereNumber('id');
    Route::middleware('permiso:eliminar_unidades')
        ->delete('/unidades/{id}', [UnidadController::class, 'destroy'])->whereNumber('id');
    Route::middleware('permiso:cambiar_estado_unidad')
        ->put('/unidades/{id}/cambiar-estado', [UnidadController::class, 'cambiarEstado'])->whereNumber('id');
    Route::middleware('permiso:cambiar_zona_unidad')
        ->post('/unidades/{id}/cambiar-zona', [UnidadController::class, 'cambiarZona'])->whereNumber('id');
    Route::middleware('permiso:asignar_operador_unidad')->group(function () {
        Route::post('/unidades/{id}/asignar-operador', [UnidadController::class, 'asignarOperador'])->whereNumber('id');
        Route::post('/unidades/{id}/quitar-operador',  [UnidadController::class, 'quitarOperador'])->whereNumber('id');
    });

    // ── VIAJES ────────────────────────────────────────────────────────────────
    Route::prefix('viajes')->group(function () {

        Route::middleware('permiso:ver_viajes')->group(function () {
            Route::get('/',          [AgregarViajeController::class, 'index']);
            Route::get('pendientes', [ViajeController::class, 'obtenerViajesPendientes']);
            Route::get('{id}',       [AgregarViajeController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('permiso:ver_historial_viaje')->group(function () {
            Route::get('{id}/cadena',       [ViajeController::class, 'obtenerCadena'])->whereNumber('id');
            Route::get('{id}/historial',    [ViajeController::class, 'historialViaje'])->whereNumber('id');
            Route::get('{id}/finalizacion', [ViajeController::class, 'getFinalizacion'])->whereNumber('id');
        });
        Route::middleware('permiso:crear_viajes')
            ->post('/', [AgregarViajeController::class, 'store']);
        Route::middleware('permiso:editar_viajes')
            ->put('{id}', [AgregarViajeController::class, 'update'])->whereNumber('id');
        Route::middleware('permiso:eliminar_viajes')
            ->delete('{id}', [AgregarViajeController::class, 'destroy'])->whereNumber('id');
        Route::middleware('permiso:aprobar_viajes')->group(function () {
            Route::post('{id}/aprobar',             [ViajeController::class, 'aprobar'])->whereNumber('id');
            Route::post('{id}/calcular-asignacion', [ViajeController::class, 'calcularAsignacion'])->whereNumber('id');
        });
        Route::middleware('permiso:rechazar_viajes')
            ->post('{id}/rechazar', [ViajeController::class, 'rechazar'])->whereNumber('id');
        Route::middleware('permiso:reasignar_viajes')
            ->post('{id}/reasignar', [ViajeController::class, 'reasignar'])->whereNumber('id');
        Route::middleware('permiso:iniciar_viajes')
            ->post('{id}/iniciar', [ViajeController::class, 'iniciarViaje'])->whereNumber('id');
        Route::middleware('permiso:finalizar_viajes')
            ->post('{id}/finalizar', [ViajeController::class, 'finalizar'])->whereNumber('id');
        Route::middleware('permiso:cambiar_tarifa_viaje')
            ->post('{id}/cambiar-tarifa', [ViajeController::class, 'cambiarTarifa'])->whereNumber('id');
        Route::middleware('permiso:cancelar_viajes')
            ->post('{id}/cancelar', [ViajeController::class, 'cancelarViaje'])->whereNumber('id');
    });

    // ── USUARIOS Y ROLES ──────────────────────────────────────────────────────
    Route::middleware('permiso:ver_roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
    });
    Route::middleware('permiso:crear_roles')
        ->post('/roles', [RoleController::class, 'store']);

    Route::middleware('permiso:ver_usuarios')->group(function () {
        Route::get('/usuarios',      [UsuarioController::class, 'index']);
        Route::get('/usuarios/{id}', [UsuarioController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permiso:crear_usuarios')
        ->post('/usuarios', [UsuarioController::class, 'store']);
    Route::middleware('permiso:editar_usuarios')
        ->put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
    Route::middleware('permiso:eliminar_usuarios')
        ->delete('/usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id');

    // ── ADMIN: CONFIGURADOR DE PERMISOS ───────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::middleware('permiso:sincronizar_permisos')
            ->post('/permisos/sincronizar', [PermisoController::class, 'sincronizar']);
        Route::middleware('permiso:ver_roles')->group(function () {
            Route::get('/permisos/roles',         [PermisoController::class, 'roles']);
            Route::put('/permisos/roles/{rolId}', [PermisoController::class, 'actualizarRol']);
            Route::get('/permisos/usuarios/{id}', [PermisoController::class, 'permisosUsuario']);
            Route::put('/permisos/usuarios/{id}', [PermisoController::class, 'actualizarUsuario']);
        });
    });
});