<?php

// app/Http/Controllers/Api/ZonaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    // Obtener todas las zonas
    public function index()
    {
        return Zona::all();
    }

    // Obtener zona por ID
    public function show($id)
    {
        return Zona::findOrFail($id);
    }

    // Crear una nueva zona
    public function store(Request $request)
    {
        $request->validate([
            'nombre_zona' => 'required|string|max:255',
        ]);

        $zona = Zona::create($request->all());
        return response()->json($zona, 201);
    }

    // Actualizar una zona
    public function update(Request $request, $id)
    {
        $zona = Zona::findOrFail($id);
        $zona->update($request->all());
        return response()->json($zona);
    }

    // Eliminar una zona
    public function destroy($id)
    {
        $zona = Zona::findOrFail($id);
        $zona->delete();
        return response()->json(null, 204);
    }
}