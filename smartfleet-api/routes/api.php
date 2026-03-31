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

// ── PANEL ─────────────────────────────────────────────────────────────────────
Route::get('/panel/resumen', [PanelController::class, 'resumen']);

// ── ZONAS ─────────────────────────────────────────────────────────────────────
Route::get('/zonas',         [ZonaController::class, 'index']);
Route::get('/zonas/{id}',    [ZonaController::class, 'show']);
Route::post('/zonas',        [ZonaController::class, 'store']);
Route::put('/zonas/{id}',    [ZonaController::class, 'update']);
Route::delete('/zonas/{id}', [ZonaController::class, 'destroy']);

// ── CLIENTES ──────────────────────────────────────────────────────────────────
Route::get('/clientes/selector', [ClienteController::class, 'listadoSelector']);
Route::get('/clientes',          [ClienteController::class, 'index']);

// ── UNIDADES ──────────────────────────────────────────────────────────────────
Route::get('/unidades/{id}/historial-operadores',      [UnidadController::class, 'historialOperadores']);
Route::post('/unidades/{id}/cambiar-zona',             [UnidadController::class, 'cambiarZona']);
Route::get('/unidades/{id}/detalle',                   [UnidadController::class, 'showConDetalle']);
Route::get('/unidades/{id}/historial-zona',            [UnidadController::class, 'historialZona']);
Route::get('/unidades/{id}/historial-estado-filtrado', [UnidadController::class, 'historialEstadoFiltrado']);
Route::post('/unidades/{id}/asignar-operador',         [UnidadController::class, 'asignarOperador']);
Route::post('/unidades/{id}/quitar-operador',          [UnidadController::class, 'quitarOperador']);
Route::put('/unidades/{id}/cambiar-estado',            [UnidadController::class, 'cambiarEstado']);
Route::get('/unidades',         [UnidadController::class, 'index']);
Route::get('/unidades/{id}',    [UnidadController::class, 'show']);
Route::post('/unidades',        [UnidadController::class, 'store']);
Route::put('/unidades/{id}',    [UnidadController::class, 'update']);
Route::delete('/unidades/{id}', [UnidadController::class, 'destroy']);

// ── OPERADORES ────────────────────────────────────────────────────────────────
Route::get('operadores/{id}/historial-estado',      [OperadorController::class, 'historialEstado']);
Route::get('operadores/{id}/historial-zona',        [OperadorController::class, 'historialZona']);
Route::post('operadores/{id}/cambiar-estado',       [OperadorController::class, 'cambiarEstado']);
Route::post('operadores/{id}/cambiar-zona',         [OperadorController::class, 'cambiarZona']);
Route::get('/operadores/{id_operador}/movimientos', [ViajeController::class, 'movimientosOperador']);
Route::get('/operadores',         [OperadorController::class, 'index']);
Route::get('/operadores/{id}',    [OperadorController::class, 'show']);
Route::post('/operadores',        [OperadorController::class, 'store']);
Route::put('/operadores/{id}',    [OperadorController::class, 'update']);
Route::delete('/operadores/{id}', [OperadorController::class, 'destroy']);

// ── CUOTAS ────────────────────────────────────────────────────────────────────
Route::get('/operadores/{idOperador}/cuotas', [OperadorCuotaController::class, 'porOperador']);
Route::get('/operador-cuotas',         [OperadorCuotaController::class, 'index']);
Route::get('/operador-cuotas/{id}',    [OperadorCuotaController::class, 'show']);
Route::post('/operador-cuotas',        [OperadorCuotaController::class, 'store']);
Route::put('/operador-cuotas/{id}',    [OperadorCuotaController::class, 'update']);
Route::delete('/operador-cuotas/{id}', [OperadorCuotaController::class, 'destroy']);

// ── CERTIFICACIONES ───────────────────────────────────────────────────────────
Route::get('/certificaciones/cliente/{clienteId}', [CertificacionController::class, 'getCertificacionesPorCliente']);
Route::apiResource('certificaciones', CertificacionController::class);

// ── RUTAS Y LICENCIAS ─────────────────────────────────────────────────────────
Route::apiResource('rutas',     RutaController::class);
Route::apiResource('licencias', LicenciaController::class);

// ── VIAJES ────────────────────────────────────────────────────────────────────


// ── VIAJES ────────────────────────────────────────────────────────────────────
Route::middleware('auth:api')->prefix('viajes')->group(function () {

    Route::get('pendientes', [ViajeController::class, 'obtenerViajesPendientes']);

    Route::get('{id}/cadena',               [ViajeController::class, 'obtenerCadena'])->whereNumber('id');
    Route::get('{id}/historial',            [ViajeController::class, 'historialViaje'])->whereNumber('id');
    Route::get('{id}/finalizacion',         [ViajeController::class, 'getFinalizacion'])->whereNumber('id');

    Route::post('{id}/calcular-asignacion', [ViajeController::class, 'calcularAsignacion'])->whereNumber('id');
    Route::post('{id}/aprobar',             [ViajeController::class, 'aprobar'])->whereNumber('id');
    Route::post('{id}/rechazar',            [ViajeController::class, 'rechazar'])->whereNumber('id');
    Route::post('{id}/reasignar',           [ViajeController::class, 'reasignar'])->whereNumber('id');
    Route::post('{id}/iniciar',             [ViajeController::class, 'iniciarViaje'])->whereNumber('id');
    Route::post('{id}/finalizar',           [ViajeController::class, 'finalizar'])->whereNumber('id');
    Route::post('{id}/cambiar-tarifa',      [ViajeController::class, 'cambiarTarifa'])->whereNumber('id');
    Route::post('{id}/cancelar',            [ViajeController::class, 'cancelarViaje'])->whereNumber('id');

    Route::get('/',       [AgregarViajeController::class, 'index']);
    Route::post('/',      [AgregarViajeController::class, 'store']);
    Route::get('{id}',    [AgregarViajeController::class, 'show'])->whereNumber('id');
    Route::put('{id}',    [AgregarViajeController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [AgregarViajeController::class, 'destroy'])->whereNumber('id');
});

// ── PING ──────────────────────────────────────────────────────────────────────
Route::get('/ping', fn() => response()->json(['ok' => true, 'msg' => 'pong']));

// ── AUTH ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:api')->group(function () {
        Route::get('/me',       [AuthController::class, 'me']);
        Route::post('/logout',  [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// ── USUARIOS Y ROLES ──────────────────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles',  [RoleController::class, 'index']);

        Route::post('/usuarios',        [UsuarioController::class, 'store']);
        Route::get('/usuarios',         [UsuarioController::class, 'index']);
        Route::get('/usuarios/{id}',    [UsuarioController::class, 'show']);
        Route::put('/usuarios/{id}',    [UsuarioController::class, 'update']);
        Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
    });
});