<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // public function register(Request $request)
    // {
    //     $v = Validator::make($request->all(), [
    //         'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
    //         'apellidos' => ['required', 'string', 'max:255'],
    //         'contrasena' => ['required', 'string', 'min:6'],
    //         'estado' => ['nullable', 'string', 'max:30'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'ok' => false,
    //             'errors' => $v->errors()
    //         ], 422);
    //     }

    //     $usuario = Usuario::create([
    //         'email' => $request->email,
    //         'apellidos' => $request->apellidos,
    //         'contrasena' => Hash::make($request->contrasena),
    //         'estado' => $request->estado ?? 'activo',
    //     ]);

    //     // Opcional: loguear al registrar
    //     $token = auth('api')->login($usuario);

    //     return $this->respondWithToken($token, $usuario, 201);
    // }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->contrasena, // JWTAuth espera "password"
        ];

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'ok' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }
        
        $usuario = auth('api')->user();
        //dd($usuario->estado); 
        // Si quieres bloquear inactivos
        if (($usuario->estado ?? 'ACTIVO') !== 'ACTIVO') {
            auth('api')->logout();
            return response()->json([
                'ok' => false,
                'message' => 'Usuario inactivo'
            ], 403);
        }

        return $this->respondWithToken($token, $usuario);
    }

    public function me()
    {
        return response()->json([
            'ok' => true,
            'usuario' => auth('api')->user(),
        ]);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'ok' => true,
            'message' => 'Sesión cerrada'
        ]);
    }

    public function refresh()
    {
        $token = auth('api')->refresh();

        return $this->respondWithToken($token, auth('api')->user());
    }

    private function respondWithToken(string $token, $usuario, int $status = 200)
    {
        return response()->json([
            'ok' => true,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'usuario' => $usuario,
        ], $status);
    }
}
