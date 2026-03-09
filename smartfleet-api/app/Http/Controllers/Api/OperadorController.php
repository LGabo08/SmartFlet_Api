<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperadorController extends Controller
{
    public function index()
    {
        $operadores = Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])->get();

        return response()->json([
            'ok' => true,
            'operadores' => $operadores
        ]);
    }

    public function show($id)
    {
        $operador = Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])->find($id);

        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

        return response()->json([
            'ok' => true,
            'operador' => $operador
        ]);
    }

    public function store(Request $request)
    {
$validatedData = $request->validate([
    'numero_empleado' => 'required|string|max:255',
    'nombres' => 'required|string|max:255',
    'apellidos' => 'required|string|max:255',

    'fk_zona_actual' => 'nullable|exists:zona,id_zona',  // Cambiar a nullable
    'fk_unidad_asignada' => 'nullable|exists:unidad,id_unidad', // Asegurarse de que 'unidad' también sea nullable si es opcional

    'estado_operador' => 'required|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO',

    'fk_tipo_licencia' => 'nullable|exists:licencia,id_licencia', // Cambiar a nullable
    'vigencia_licencia' => 'nullable|date',  // Cambiar a nullable

    'certificaciones' => 'sometimes|array',  // Certificaciones no es obligatorio, solo se pasa si viene en el payload
    'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
]);
        // separar certs del payload para no romper mass-assignment
        $certIds = $validatedData['certificaciones'] ?? [];
        unset($validatedData['certificaciones']);

        return DB::transaction(function () use ($validatedData, $certIds) {
            $operador = Operador::create($validatedData);

            // ✅ asignar certificaciones (si vienen)
            if (!empty($certIds)) {
                $operador->certificaciones()->sync($certIds);
            }

            return response()->json([
                'ok' => true,
                'operador' => Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])
                    ->find($operador->id_operador)
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $operador = Operador::find($id);

        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

 $validatedData = $request->validate([
    'numero_empleado' => 'required|string|max:255',
    'nombres' => 'required|string|max:255',
    'apellidos' => 'required|string|max:255',

    'fk_zona_actual' => 'nullable|exists:zona,id_zona',  // Cambiar a nullable
    'fk_unidad_asignada' => 'nullable|exists:unidad,id_unidad', // Asegurarse de que 'unidad' también sea nullable si es opcional

    'estado_operador' => 'required|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO',

    'fk_tipo_licencia' => 'nullable|exists:licencia,id_licencia', // Cambiar a nullable
    'vigencia_licencia' => 'nullable|date',  // Cambiar a nullable

    'certificaciones' => 'sometimes|array',  // Certificaciones no es obligatorio, solo se pasa si viene en el payload
    'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
]);

        $certIds = $validatedData['certificaciones'] ?? null;
        unset($validatedData['certificaciones']);

        return DB::transaction(function () use ($operador, $validatedData, $certIds) {
            // update campos del operador
            if (!empty($validatedData)) {
                $operador->update($validatedData);
            }

            // ✅ si el front manda certificaciones, hacemos sync (reemplaza)
            if (is_array($certIds)) {
                $operador->certificaciones()->sync($certIds);
            }

            return response()->json([
                'ok' => true,
                'operador' => Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])
                    ->find($operador->id_operador)
            ]);
        });
    }

    public function destroy($id)
    {
        $operador = Operador::find($id);

        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

        // ✅ opcional: al borrar operador, limpia pivote (si no tienes ON DELETE CASCADE)
        $operador->certificaciones()->detach();

        $operador->delete();

        return response()->json(['ok' => true, 'msg' => 'Operador eliminado']);
    }

    // En OperadorController.php
public function getLicencias()
{
    // Obtener todas las licencias
    $licencias = Licencia::all();

    return response()->json([
        'ok' => true,
        'licencias' => $licencias
    ]);
}
}