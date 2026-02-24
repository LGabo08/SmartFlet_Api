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
Route::get('/ping', fn() => response()->json(['ok' => true, 'msg' => 'pong']));


// routes/api.php


Route::apiResource('certificaciones', CertificacionController::class);
Route::apiResource('rutas', RutaController::class);
Route::apiResource('licencias', LicenciaController::class);

Route::prefix('viajes')->group(function () {
    Route::get('/', [AgregarViajeController::class, 'index']);  // Listar todos los viajes
    Route::post('/', [AgregarViajeController::class, 'store']); // Crear un nuevo viaje
    Route::get('{id}', [AgregarViajeController::class, 'show']); // Obtener un viaje por ID
    Route::put('{id}', [AgregarViajeController::class, 'update']); // Actualizar un viaje por ID
    Route::delete('{id}', [AgregarViajeController::class, 'destroy']); // Eliminar un viaje por ID
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
Route::post('/viajes/{id}/asignar', [ViajeController::class, 'asignar']);
Route::post('/viajes/{id}/aprobar', [ViajeController::class, 'aprobar']);
Route::post('/viajes/{id}/rechazar', [ViajeController::class, 'rechazar']);
Route::post('/viajes/{id}/reasignar', [ViajeController::class, 'reasignar']);

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
