<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RoleController;

Route::get('/ping', fn() => response()->json(['ok' => true, 'msg' => 'pong']));

/*
|--------------------------------------------------------------------------
| AUTH (público)
|--------------------------------------------------------------------------
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

    
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles',  [RoleController::class, 'index']);
    });

    
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::get('/usuarios',  [UsuarioController::class, 'index']);
    });

});
