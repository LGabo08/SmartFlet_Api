<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ruta;
use Illuminate\Http\Request;

class RutaController extends Controller
{
    // Obtener todas las rutas
public function index()
{
    $rutas = Ruta::all();  // Obtiene todas las rutas

    // Devuelve solo el array de rutas directamente, sin envolverlo en un objeto adicional
    return response()->json($rutas);
}

    // Obtener una ruta por ID
    public function show($id)
    {
        $ruta = Ruta::find($id);

        if (!$ruta) {
            return response()->json(['ok' => false, 'message' => 'Ruta no encontrada'], 404);
        }

        return response()->json(['ok' => true, 'ruta' => $ruta]);
    }

    // Crear una nueva ruta
    public function store(Request $request)
    {
        // Validar los datos recibidos
        $validatedData = $request->validate([
            'fk_zona_origen' => 'required|exists:zona,id_zona',
            'fk_zona_destino' => 'required|exists:zona,id_zona',
            'distancia_km' => 'required|numeric',
            'tarifa_operador' => 'required|numeric',
            'nombre_ruta' => 'required|string|max:255',  // Validar nombre_ruta
        ]);

        // Crear la nueva ruta
        $ruta = Ruta::create([
            'fk_zona_origen' => $validatedData['fk_zona_origen'],
            'fk_zona_destino' => $validatedData['fk_zona_destino'],
            'distancia_km' => $validatedData['distancia_km'],
            'tarifa_operador' => $validatedData['tarifa_operador'],
            'nombre_ruta' => $validatedData['nombre_ruta'],  // Agregar el nombre de la ruta
        ]);

        return response()->json(['ok' => true, 'ruta' => $ruta]);
    }

    // Actualizar una ruta por ID
    public function update(Request $request, $id)
    {
        $ruta = Ruta::find($id);

        if (!$ruta) {
            return response()->json(['ok' => false, 'message' => 'Ruta no encontrada'], 404);
        }

        // Validación de los datos recibidos
        $validatedData = $request->validate([
            'fk_zona_origen' => 'required|exists:zona,id_zona',
            'fk_zona_destino' => 'required|exists:zona,id_zona',
            'distancia_km' => 'required|numeric',
            'tarifa_operador' => 'required|numeric',
            'nombre_ruta' => 'required|string|max:255',  // Validar nombre_ruta
        ]);

        // Actualizar la ruta
        $ruta->update([
            'fk_zona_origen' => $validatedData['fk_zona_origen'],
            'fk_zona_destino' => $validatedData['fk_zona_destino'],
            'distancia_km' => $validatedData['distancia_km'],
            'tarifa_operador' => $validatedData['tarifa_operador'],
            'nombre_ruta' => $validatedData['nombre_ruta'],  // Actualizar el nombre de la ruta
        ]);

        return response()->json(['ok' => true, 'ruta' => $ruta]);
    }

    // Eliminar una ruta por ID
    public function destroy($id)
    {
        $ruta = Ruta::find($id);

        if (!$ruta) {
            return response()->json(['ok' => false, 'message' => 'Ruta no encontrada'], 404);
        }

        $ruta->delete();

        return response()->json(['ok' => true, 'message' => 'Ruta eliminada con éxito']);
    }
}