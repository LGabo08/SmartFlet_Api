<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unidad;
use Illuminate\Http\Request;

class UnidadController extends Controller
{
    public function index()
    {
        // Si quieres incluir relaciones:
        // return Unidad::with(['zona','licencia'])->get();
        return response()->json([
            'ok' => true,
            'unidades' => Unidad::all()
        ]);
    }

    public function show($id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // ✅ requerido por tu model (incrementing = false)
            'id_unidad' => 'required|integer|unique:unidad,id_unidad',

            'numero_economico' => 'required|string|max:255',
            'fk_zona_actual' => 'required|exists:zona,id_zona',
            'estado' => 'required|string|max:50',
            'fk_licencia_requerida' => 'nullable|exists:licencia,id_licencia',
        ]);

        $unidad = Unidad::create($validated);

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        $validated = $request->validate([
            'numero_economico' => 'sometimes|string|max:255',
            'fk_zona_actual' => 'sometimes|exists:zona,id_zona',
            'estado' => 'sometimes|string|max:50',
            'fk_licencia_requerida' => 'sometimes|nullable|exists:licencia,id_licencia',
        ]);

        $unidad->update($validated);

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ]);
    }

    public function destroy($id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        $unidad->delete();

        return response()->json([
            'ok' => true,
            'msg' => 'Unidad eliminada correctamente'
        ]);
    }
}