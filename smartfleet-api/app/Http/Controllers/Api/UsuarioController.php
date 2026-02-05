<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    public function index()
    {
        // ✅ Tu PK real es idUsuario (NO existe id)
        $usuarios = Usuario::orderBy('idUsuario', 'desc')->get();

        return response()->json([
            'ok' => true,
            'usuarios' => $usuarios
        ]);
    }

    public function show($id)
    {
        // ✅ find() funcionará por idUsuario si tu modelo tiene primaryKey configurado
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'usuario' => $usuario
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'      => 'required|email|unique:usuarios,email',
            'nombre'     => 'required|string|max:120',
            'apellidos'  => 'required|string|max:255',
            'contrasena' => 'required|string|min:6',
            'role_id'    => 'required|exists:roles,id',
            'estado'     => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = Usuario::create([
            'email'      => $request->email,
            'nombre'     => $request->nombre,
            'apellidos'  => $request->apellidos,
            'contrasena' => Hash::make($request->contrasena),
            'role_id'    => $request->role_id,
            'estado'     => $request->estado ?? 'activo',
        ]);

        return response()->json([
            'ok' => true,
            'usuario' => $usuario
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // ✅ IMPORTANTE: como tu PK es idUsuario, la regla unique debe indicarlo
        $validator = Validator::make($request->all(), [
            'email'      => 'required|email|unique:usuarios,email,' . $usuario->idUsuario . ',idUsuario',
            'nombre'     => 'required|string|max:120',
            'apellidos'  => 'required|string|max:255',
            'contrasena' => 'nullable|string|min:6',
            'role_id'    => 'required|exists:roles,id',
            'estado'     => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario->email = $request->email;
        $usuario->nombre = $request->nombre;
        $usuario->apellidos = $request->apellidos;
        $usuario->role_id = $request->role_id;
        $usuario->estado = $request->estado ?? $usuario->estado;

        if ($request->filled('contrasena')) {
            $usuario->contrasena = Hash::make($request->contrasena);
        }

        $usuario->save();

        return response()->json([
            'ok' => true,
            'usuario' => $usuario
        ]);
    }

    public function destroy($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $usuario->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Usuario eliminado'
        ]);
    }
}
