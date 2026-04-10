<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viaje;
use App\Models\Certificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgregarViajeController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->query('estado');

        $query = DB::table('viaje as v')
            ->join('ruta as r', 'r.id_ruta', '=', 'v.fk_ruta')
            ->leftJoin('operador as op', 'op.id_operador', '=', 'v.fk_operador')
            ->leftJoin('unidad as u', 'u.id_unidad', '=', 'v.fk_unidad')
            ->leftJoin('licencia as l', 'l.id_licencia', '=', 'v.fk_licencia_requerida')
            ->select(
                'v.id_viaje', 'v.numero_viaje', 'v.estado',
                'v.fk_operador', 'v.fk_unidad',
                'v.fecha_salida', 'v.fecha_llegada',
                'v.pago_operador', 'v.configuracion_unidad',
                'v.fk_licencia_requerida', 'v.fk_viaje_padre',
                'r.nombre_ruta',
                'l.nombre_licencia',
                DB::raw("CONCAT(op.nombres, ' ', op.apellidos) as operador_nombre"),
                'u.numero_economico',
            )
            ->orderBy('v.id_viaje', 'desc');

        if ($estado) {
            $query->where('v.estado', strtoupper($estado));
        } else {
            $query->where('v.estado', '!=', 'CANCELADO');
        }

        return response()->json(['ok' => true, 'viajes' => $query->get()], 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'numero_viaje'          => 'required|string|max:30|unique:viaje,numero_viaje',
            'fk_ruta'               => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            // ✅ FIX: nullable + array vacío permitido (min:0 en lugar de min:1)
            'certificaciones'       => 'nullable|array',
            'certificaciones.*'     => 'integer|exists:certificacion,id_certificacion',
            'configuracion_unidad'  => 'required|string|max:255',
            'cliente'               => 'required|string|max:255',
            'id_cliente'            => 'nullable|integer|exists:cliente,id_cliente',
            'producto'              => 'required|string|max:255',
            'fk_operador'           => 'nullable|exists:operador,id_operador',
            'fk_unidad'             => 'nullable|exists:unidad,id_unidad',
            'pago_operador'         => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validatedData, $request) {

            $certIds = $validatedData['certificaciones'] ?? [];
            unset($validatedData['certificaciones']);

            $viaje = Viaje::create([
                'numero_viaje'          => $validatedData['numero_viaje'],
                'fk_ruta'               => $validatedData['fk_ruta'],
                'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
                'fk_operador'           => $validatedData['fk_operador'] ?? null,
                'fk_unidad'             => $validatedData['fk_unidad']   ?? null,
                'pago_operador'         => $validatedData['pago_operador'],
                'estado'                => 'PENDIENTE',
                'configuracion_unidad'  => $validatedData['configuracion_unidad'],
                'cliente'               => $validatedData['cliente'],
                'producto'              => $validatedData['producto'],
            ]);

            // ✅ Primero intentar cargar certs por id_cliente del backend
            $clienteId = $request->input('id_cliente');
            $syncData  = [];

            if ($clienteId) {
                $certsCliente = Certificacion::where('fk_cliente', $clienteId)->get();
                foreach ($certsCliente as $cert) {
                    $syncData[$cert->id_certificacion] = ['obligatoria' => 1];
                }
            }

            // Si el frontend mandó IDs explícitos y no había certs de cliente, usarlos
            if (empty($syncData) && !empty($certIds)) {
                foreach ($certIds as $certId) {
                    $syncData[(int)$certId] = ['obligatoria' => 1];
                }
            }

            // ✅ sync vacío es válido — viaje sin certificaciones requeridas
            $viaje->certificaciones()->sync($syncData);

            return response()->json([
                'ok'    => true,
                'viaje' => Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])
                               ->find($viaje->id_viaje),
            ], 201);
        });
    }

    public function show($id)
    {
        $viaje = Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])->find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        return response()->json(['ok' => true, 'viaje' => $viaje]);
    }

    public function update(Request $request, $id)
    {
        $viaje = Viaje::find($id);

        if (!$viaje) {
            return response()->json(['ok' => false, 'message' => 'Viaje no encontrado'], 404);
        }

        $validatedData = $request->validate([
            'numero_viaje'          => 'required|string|max:30|unique:viaje,numero_viaje,' . $id . ',id_viaje',
            'fk_ruta'               => 'required|exists:ruta,id_ruta',
            'fk_licencia_requerida' => 'required|exists:licencia,id_licencia',
            // ✅ FIX: nullable + array vacío permitido
            'certificaciones'       => 'nullable|array',
            'certificaciones.*'     => 'integer|exists:certificacion,id_certificacion',
            'configuracion_unidad'  => 'sometimes|string|max:255',
            'cliente'               => 'sometimes|string|max:255',
            'producto'              => 'sometimes|string|max:255',
            'fk_operador'           => 'nullable|exists:operador,id_operador',
            'fk_unidad'             => 'nullable|exists:unidad,id_unidad',
            'pago_operador'         => 'required|numeric|min:0',
            'estado'                => 'nullable|string|in:PENDIENTE,ASIGNADO,EN_CURSO,TERMINADO,CANCELADO',
        ]);

        return DB::transaction(function () use ($viaje, $validatedData) {

            $certIds = $validatedData['certificaciones'] ?? null;
            unset($validatedData['certificaciones']);

            $viaje->update([
                'numero_viaje'          => $validatedData['numero_viaje'],
                'fk_ruta'               => $validatedData['fk_ruta'],
                'fk_licencia_requerida' => $validatedData['fk_licencia_requerida'],
                'fk_operador'           => $validatedData['fk_operador'] ?? null,
                'fk_unidad'             => $validatedData['fk_unidad']   ?? null,
                'pago_operador'         => $validatedData['pago_operador'],
                'estado'                => $validatedData['estado'] ?? $viaje->estado,
                'configuracion_unidad'  => $validatedData['configuracion_unidad'] ?? $viaje->configuracion_unidad,
                'cliente'               => $validatedData['cliente'] ?? $viaje->cliente,
                'producto'              => $validatedData['producto'] ?? $viaje->producto,
            ]);

            // ✅ Si mandan certificaciones (incluso vacías), sincronizamos
            if (is_array($certIds)) {
                $syncData = [];
                foreach ($certIds as $idCert) {
                    $syncData[(int)$idCert] = ['obligatoria' => 1];
                }
                $viaje->certificaciones()->sync($syncData);
            }

            return response()->json([
                'ok'    => true,
                'viaje' => Viaje::with(['ruta', 'licencia', 'certificaciones', 'operador', 'unidad'])
                               ->find($viaje->id_viaje),
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