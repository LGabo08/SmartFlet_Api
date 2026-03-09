<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viaje;
use App\Models\Certificacion; // Asegúrate de importar el modelo de certificación
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgregarViajeController extends Controller
{
    // Mostrar todos los viajes con sus relaciones
    public function index()
    {
        $viajes = Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])->get();

        return response()->json(['ok' => true, 'viajes' => $viajes]);
    }

    // Crear un nuevo viaje
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'numero_viaje' => 'required|string|max:30|unique:viaje,numero_viaje',
            'fk_ruta' => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            // ✅ MULTI CERT
            'certificaciones' => 'required|array|min:1',
            'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
            'configuracion_unidad' => 'required|string|max:255', // Nuevo campo
            'cliente' => 'required|string|max:255',              // Nuevo campo
            'producto' => 'required|string|max:255',             // Nuevo campo
            'fk_operador' => 'nullable|exists:operador,id_operador',
            'fk_unidad' => 'nullable|exists:unidad,id_unidad',
            'pago_operador' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validatedData) {

            $certIds = $validatedData['certificaciones'];
            unset($validatedData['certificaciones']);

            // Crear el viaje
            $viaje = Viaje::create([
                'numero_viaje' => $validatedData['numero_viaje'],
                'fk_ruta' => $validatedData['fk_ruta'],
                'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
                'fk_operador' => $validatedData['fk_operador'] ?? null,
                'fk_unidad' => $validatedData['fk_unidad'] ?? null,
                'pago_operador' => $validatedData['pago_operador'],
                'estado' => 'PENDIENTE',
                'configuracion_unidad' => $validatedData['configuracion_unidad'],
                'cliente' => $validatedData['cliente'],
                'producto' => $validatedData['producto'],
            ]);

            // Filtrar las certificaciones por cliente
            $clienteId = $validatedData['cliente'];
            $certificaciones = Certificacion::where('fk_cliente', $clienteId)->get();

            // Guardar certificaciones en pivote
            $syncData = [];
            foreach ($certificaciones as $certificacion) {
                $syncData[$certificacion->id_certificacion] = ['obligatoria' => 1];
            }

            $viaje->certificaciones()->sync($syncData);

            return response()->json([
                'ok' => true,
                'viaje' => Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])
                    ->find($viaje->id_viaje)
            ], 201);
        });
    }

    // Obtener un viaje por ID
    public function show($id)
    {
        $viaje = Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])->find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    // Actualizar un viaje
    public function update(Request $request, $id)
    {
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        $validatedData = $request->validate([
            'numero_viaje' => 'required|string|max:30|unique:viaje,numero_viaje,' . $id . ',id_viaje',
            'fk_ruta' => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            // ✅ MULTI CERT
            'certificaciones' => 'sometimes|array|min:1',
            'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
            'configuracion_unidad' => 'sometimes|string|max:255',
            'cliente' => 'sometimes|string|max:255',
            'producto' => 'sometimes|string|max:255',
            'fk_operador' => 'nullable|exists:operador,id_operador',
            'fk_unidad' => 'nullable|exists:unidad,id_unidad',
            'pago_operador' => 'required|numeric|min:0',
            'estado' => 'nullable|string|in:PENDIENTE,ASIGNADO,EN_CURSO,TERMINADO,CANCELADO',
        ]);

        return DB::transaction(function () use ($viaje, $validatedData) {

            $certIds = $validatedData['certificaciones'] ?? null;
            unset($validatedData['certificaciones']);

            $viaje->update([
                'numero_viaje' => $validatedData['numero_viaje'],
                'fk_ruta' => $validatedData['fk_ruta'],
                'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
                'fk_operador' => $validatedData['fk_operador'] ?? null,
                'fk_unidad' => $validatedData['fk_unidad'] ?? null,
                'pago_operador' => $validatedData['pago_operador'],
                'estado' => $validatedData['estado'] ?? $viaje->estado,
                'configuracion_unidad' => $validatedData['configuracion_unidad'] ?? $viaje->configuracion_unidad,
                'cliente' => $validatedData['cliente'] ?? $viaje->cliente,
                'producto' => $validatedData['producto'] ?? $viaje->producto,
            ]);

            // Si mandan certificaciones, sincronizamos
            if (is_array($certIds)) {
                $syncData = [];
                foreach ($certIds as $idCert) {
                    $syncData[(int)$idCert] = ['obligatoria' => 1];
                }
                $viaje->certificaciones()->sync($syncData);
            }

            return response()->json([
                'ok' => true,
                'viaje' => Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])
                    ->find($viaje->id_viaje)
            ]);
        });
    }

    public function destroy($id)
    {
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        return DB::transaction(function () use ($viaje) {
            $viaje->certificaciones()->detach();
            $viaje->delete();

            return response()->json(['ok' => true, 'message' => 'Viaje eliminado con éxito']);
        });
    }
}