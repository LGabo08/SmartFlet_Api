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


    Route::get('/operador-cuotas', [OperadorCuotaController::class, 'index']);
    Route::get('/operador-cuotas/{id}', [OperadorCuotaController::class, 'show']);
    Route::post('/operador-cuotas', [OperadorCuotaController::class, 'store']);
    Route::put('/operador-cuotas/{id}', [OperadorCuotaController::class, 'update']);
    Route::delete('/operador-cuotas/{id}', [OperadorCuotaController::class, 'destroy']);

    Route::get('/operadores/{idOperador}/cuotas', [OperadorCuotaController::class, 'porOperador']);

Route::get('/clientes', [ClienteController::class, 'index']);
Route::get('/unidades', [UnidadController::class, 'index']);
Route::get('/unidades/{id}', [UnidadController::class, 'show']);
Route::post('/unidades', [UnidadController::class, 'store']);
Route::put('/unidades/{id}', [UnidadController::class, 'update']);
Route::delete('/unidades/{id}', [UnidadController::class, 'destroy']);

Route::get('/ping', fn() => response()->json(['ok' => true, 'msg' => 'pong']));



Route::get('/operadores', [OperadorController::class, 'index']);  // Obtener todos los operadores
Route::get('/operadores/{id}', [OperadorController::class, 'show']);  // Obtener un operador por ID
Route::post('/operadores', [OperadorController::class, 'store']);  // Crear un nuevo operador
Route::put('/operadores/{id}', [OperadorController::class, 'update']);  // Actualizar un operador
Route::delete('/operadores/{id}', [OperadorController::class, 'destroy']);  // Eliminar un operador

Route::apiResource('certificaciones', CertificacionController::class);
Route::apiResource('rutas', RutaController::class);
Route::apiResource('licencias', LicenciaController::class);
Route::get('/certificaciones/cliente/{clienteId}', [CertificacionController::class, 'getCertificacionesPorCliente']);

Route::prefix('viajes')->group(function () {

    // ✅ Primero rutas “fijas” (no variables)
    Route::get('pendientes', [ViajeController::class, 'obtenerViajesPendientes']);

    // ✅ Luego CRUD
    Route::get('/', [AgregarViajeController::class, 'index']);
    Route::post('/', [AgregarViajeController::class, 'store']);

    // ✅ Rutas con parámetro restringido
    Route::get('{id}', [AgregarViajeController::class, 'show'])->whereNumber('id');
    Route::put('{id}', [AgregarViajeController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [AgregarViajeController::class, 'destroy'])->whereNumber('id');

     Route::post('{id}/calcular-asignacion', [ViajeController::class, 'calcularAsignacion'])->whereNumber('id');
    // ✅ Acciones con parámetro restringido
    //Route::post('{id}/asignar', [ViajeController::class, 'asignar'])->whereNumber('id');
    Route::post('{id}/aprobar', [ViajeController::class, 'aprobar'])->whereNumber('id');
    Route::post('{id}/rechazar', [ViajeController::class, 'rechazar'])->whereNumber('id');
    Route::post('{id}/reasignar', [ViajeController::class, 'reasignar'])->whereNumber('id');
});
/*
|---------------------------------------------------------------------------
| AUTH (público)
|---------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});


Route::middleware('auth:api')->group(function () {

    // Aquí comentamos temporalmente la protección con 'role:ADMIN'
    // Ruta para manejar roles (solo admin debería poder hacerlo)
    Route::middleware('role:ADMIN')->group(function () {
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles',  [RoleController::class, 'index']);
    });

    // Aquí comentamos temporalmente la protección con 'role:ADMIN' para permitir la creación de usuarios
    Route::middleware('role:ADMIN')->group(function () {
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::get('/usuarios',  [UsuarioController::class, 'index']);
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
    });

});
