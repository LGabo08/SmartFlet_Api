<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email|unique:usuarios,email',
            'apellidos'   => 'required|string|max:255',
            'contrasena'  => 'required|string|min:6',
            'role_id'      => 'required|exists:roles,id',
            'estado'      => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = Usuario::create([
            'email'       => $request->email,
            'apellidos'   => $request->apellidos,
            'contrasena'  => Hash::make($request->contrasena),
            'role_id'      => $request->role_id,
            'estado'      => $request->estado ?? 'activo',
        ]);

        return response()->json([
            'ok' => true,
            'usuario' => $usuario
        ], 201);
    }
}
