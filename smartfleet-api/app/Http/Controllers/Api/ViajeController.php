<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viaje;
use Illuminate\Http\Request;

class AgregarViajeController extends Controller
{
    // Mostrar todos los viajes
    public function index()
    {
        // Obtener los viajes con las relaciones (ruta, licencia, certificación, operador, unidad)
        $viajes = Viaje::with(['ruta', 'licencia', 'certificacion', 'operador', 'unidad'])->get();

        return response()->json(['ok' => true, 'viajes' => $viajes]);
    }

    // Crear un nuevo viaje
    public function store(Request $request)
    {
        // Validar los datos recibidos
       $validatedData = $request->validate([
    'numero_viaje' => 'required|string|max:30|unique:viaje,numero_viaje',
    'fk_ruta' => 'required|exists:ruta,id_ruta',
    'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
    'fk_certificacion_requerida' => 'required|exists:certificacion,id_certificacion',
    'fk_operador' => 'required|exists:operador,id_operador',
    'fk_unidad' => 'required|exists:unidad,id_unidad',
    'fecha_salida' => 'nullable|date',
    'fecha_llegada' => 'nullable|date',
    'estado' => 'required|string|in:PENDIENTE,ASIGNADO,EN_CURSO,TERMINADO,CANCELADO',
    'pago_operador' => 'required|numeric|min:0',
]);

        // Crear un nuevo viaje
        $viaje = Viaje::create([
            'numero_viaje' => $validatedData['numero_viaje'],
            'fk_ruta' => $validatedData['fk_ruta'],
            'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
            'fk_certificacion_requerida' => $validatedData['fk_certificacion_requerida'],
            'fk_operador' => $validatedData['fk_operador'],
            'fk_unidad' => $validatedData['fk_unidad'],
            'fecha_salida' => $validatedData['fecha_salida'],
            'fecha_llegada' => $validatedData['fecha_llegada'],
            'estado' => $validatedData['estado'],
            'pago_operador' => $validatedData['pago_operador'],
        ]);

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Obtener un viaje por ID
    public function show($id)
    {
        $viaje = Viaje::with(['ruta', 'licencia', 'certificacion', 'operador', 'unidad'])->find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Actualizar un viaje por ID
    public function update(Request $request, $id)
    {
        // Buscar el viaje a actualizar
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        // Validación de los datos recibidos
        $validatedData = $request->validate([
            'numero_viaje' => 'required|string|max:30|unique:viaje,numero_viaje,' . $id,
            'fk_ruta' => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            'fk_certificacion_requerida' => 'required|exists:certificacion,id_certificacion',
            'fk_operador' => 'required|exists:operador,id_operador',
            'fk_unidad' => 'required|exists:unidad,id_unidad',
            'fecha_salida' => 'required|date',
            'fecha_llegada' => 'required|date',
            'estado' => 'nullable|string|in:PENDIENTE,ASIGNADO,EN_CURSO,TERMINADO,CANCELADO',
            'pago_operador' => 'required|numeric|min:0',
        ]);

        // Actualizar el viaje
        $viaje->update([
            'numero_viaje' => $validatedData['numero_viaje'],
            'fk_ruta' => $validatedData['fk_ruta'],
            'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
            'fk_certificacion_requerida' => $validatedData['fk_certificacion_requerida'],
            'fk_operador' => $validatedData['fk_operador'],
            'fk_unidad' => $validatedData['fk_unidad'],
            'fecha_salida' => $validatedData['fecha_salida'],
            'fecha_llegada' => $validatedData['fecha_llegada'],
            'estado' => $validatedData['estado'] ?? 'PENDIENTE',  // Si el estado no se pasa, se mantiene como 'PENDIENTE'
            'pago_operador' => $validatedData['pago_operador'],
        ]);

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Eliminar un viaje por ID
    public function destroy($id)
    {
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        $viaje->delete();

        return response()->json(['ok' => true, 'message' => 'Viaje eliminado con éxito']);
    }
}