<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::all(), 200);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:60', 'unique:roles,nombre'],
           
            'descripcion' => ['nullable', 'string', 'max:255'], 
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $v->errors(),
            ], 422);
        }

        $role = Role::create([
            'nombre' => $request->nombre,
            
            'description' => $request->description ?? null,
           
        ]);

        return response()->json([
            'ok' => true,
            'role' => $role,
        ], 201);
    }
}
