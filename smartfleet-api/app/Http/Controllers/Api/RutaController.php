<?php

// app/Http/Controllers/RutaController.php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ruta;
use Illuminate\Http\Request;

class RutaController extends Controller
{
    public function index()
    {
        return Ruta::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fk_zona_origen' => 'required|exists:zonas,id',
            'fk_zona_destino' => 'required|exists:zonas,id',
            'distancia_km' => 'required|numeric',
            'tarifa_operador' => 'required|numeric',
        ]);

        return Ruta::create($validated);
    }

    public function show($id)
    {
        return Ruta::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $ruta = Ruta::findOrFail($id);

        $validated = $request->validate([
            'fk_zona_origen' => 'required|exists:zonas,id',
            'fk_zona_destino' => 'required|exists:zonas,id',
            'distancia_km' => 'required|numeric',
            'tarifa_operador' => 'required|numeric',
        ]);

        $ruta->update($validated);

        return $ruta;
    }

    public function destroy($id)
    {
        Ruta::destroy($id);

        return response()->json(['message' => 'Ruta eliminada correctamente']);
    }
}