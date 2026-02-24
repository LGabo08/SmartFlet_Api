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
        // Obtener todos los viajes con sus relaciones (ruta, licencia, etc.)
        $viajes = Viaje::with(['ruta', 'licencia', 'certificaciones'])->get();

        return response()->json(['ok' => true, 'viajes' => $viajes]);
    }

    // Crear un nuevo viaje
    public function store(Request $request)
    {
        // Validación de los datos recibidos
        $validatedData = $request->validate([
            'numero_viaje' => 'required|string|max:30|unique:viaje,numero_viaje',
            'fk_ruta' => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            'fk_certificacion_requerida' => 'required|exists:certificacion,id_certificacion', // Se mantiene solo una certificación
            'pago_operador' => 'required|numeric|min:0',
        ]);

        // Crear un nuevo viaje
        $viaje = Viaje::create([
            'numero_viaje' => $validatedData['numero_viaje'],
            'fk_ruta' => $validatedData['fk_ruta'],
            'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
            'fk_certificacion_requerida' => $validatedData['fk_certificacion_requerida'], // Asignamos la única certificación
            'pago_operador' => $validatedData['pago_operador'],
            'estado' => 'PENDIENTE',  // Estado por defecto
        ]);

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Obtener un viaje por ID
    public function show($id)
    {
        $viaje = Viaje::with(['ruta', 'licencia', 'certificaciones'])->find($id);

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
            'fk_certificacion_requerida' => 'required|array',
            'fk_certificacion_requerida.*' => 'integer|exists:certificacion,id_certificacion',
            'pago_operador' => 'required|numeric|min:0',
            'estado' => 'nullable|string|in:PENDIENTE,ASIGNADO,EN_CURSO,TERMINADO,CANCELADO',
        ]);

        // Actualizar el viaje
        $viaje->update([
            'numero_viaje' => $validatedData['numero_viaje'],
            'fk_ruta' => $validatedData['fk_ruta'],
            'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
            'pago_operador' => $validatedData['pago_operador'],
            'estado' => $validatedData['estado'] ?? 'PENDIENTE',  // Si el estado no se pasa, se mantiene como 'PENDIENTE'
        ]);

        // Actualizar las certificaciones asociadas
        $viaje->certificaciones()->sync($validatedData['fk_certificacion_requerida']);  // Sincroniza las certificaciones

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Eliminar un viaje por ID
    public function destroy($id)
    {
        // Buscar el viaje a eliminar
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        // Eliminar el viaje
        $viaje->delete();

        return response()->json(['ok' => true, 'message' => 'Viaje eliminado con éxito']);
    }
}