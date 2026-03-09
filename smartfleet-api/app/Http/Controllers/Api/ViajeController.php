<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Viaje;
use App\Models\Operador;
use App\Models\Unidad;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ViajeController extends Controller
{
    // Obtener viajes pendientes (con ruta/licencia/certificaciones/pago)
    public function obtenerViajesPendientes()
    {
        $rows = DB::table('viaje as v')
            ->join('ruta as r', 'r.id_ruta', '=', 'v.fk_ruta')
            ->leftJoin('licencia as l', 'l.id_licencia', '=', 'v.fk_licencia_requerida')
            ->leftJoin('viaje_certificacion as vc', 'vc.fk_viaje', '=', 'v.id_viaje')
            ->leftJoin('certificacion as c', 'c.id_certificacion', '=', 'vc.fk_certificacion')
            ->select(
                'v.id_viaje',
                'v.numero_viaje',
                'v.estado',

                'v.fk_ruta',
                'r.nombre_ruta',
                'r.fk_zona_origen as origen_zona_id',
                'r.fk_zona_destino',
                'r.distancia_km',
                'r.tarifa_operador',

                'v.fk_licencia_requerida',
                'l.nombre_licencia',

                'c.id_certificacion as cert_id',
                'c.nombre_certificacion as cert_nombre',

                'v.pago_operador'
            )
            ->where('v.estado', 'PENDIENTE')
            ->orderBy('v.id_viaje', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'ok' => false,
                'msg' => 'No hay viajes pendientes'
            ], 404);
        }

        $viajes = $rows
            ->groupBy('id_viaje')
            ->map(function ($items) {
                $first = $items->first();

                $certs = $items->filter(fn($x) => !is_null($x->cert_id))
                    ->map(fn($x) => [
                        'id_certificacion' => (int)$x->cert_id,
                        'nombre_certificacion' => $x->cert_nombre,
                    ])
                    ->unique('id_certificacion')
                    ->values();

                return [
                    'id_viaje' => (int)$first->id_viaje,
                    'numero_viaje' => $first->numero_viaje,
                    'estado' => $first->estado,

                    'fk_ruta' => (int)$first->fk_ruta,
                    'nombre_ruta' => $first->nombre_ruta,
                    'origen_zona_id' => (int)$first->origen_zona_id,
                    'fk_zona_destino' => (int)$first->fk_zona_destino,
                    'distancia_km' => (float)$first->distancia_km,
                    'tarifa_operador' => (float)$first->tarifa_operador,

                    'fk_licencia_requerida' => $first->fk_licencia_requerida ? (int)$first->fk_licencia_requerida : null,
                    'nombre_licencia' => $first->nombre_licencia ?? null,

                    'certificaciones' => $certs,

                    'pago_operador' => $first->pago_operador !== null ? (float)$first->pago_operador : null,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'viajes' => $viajes
        ], 200);
    }

    // Calcular asignación (payload completo + TOP enriquecido con datos reales)
    public function calcularAsignacion($id_viaje)
    {
        $viaje = Viaje::with(['ruta', 'certificaciones'])
            ->where('id_viaje', $id_viaje)
            ->first();

        if (!$viaje || !$viaje->ruta) {
            return response()->json([
                'ok' => false,
                'msg' => 'Viaje o ruta no encontrada'
            ], 404);
        }

        $periodo = DB::table('operador_cuota')->max('periodo') ?? now()->format('Ym');

        $rechazados = DB::table('rechazos_operador')
            ->where('fk_viaje', (int)$id_viaje)
            ->pluck('fk_operador')
            ->map(fn($x) => (int)$x)
            ->unique()
            ->values()
            ->all();

        // Operadores
        $operadores = DB::table('operador')
            ->select([
                'id_operador',
                'estado_operador',
                'fk_zona_actual',
                'vigencia_licencia',
                'fk_unidad_asignada',
                'fk_tipo_licencia'
            ])
            ->get()
            ->map(fn($o) => (array)$o)
            ->toArray();

        // Unidades
        $unidades = DB::table('unidad')
            ->select([
                'id_unidad',
                'numero_economico',
                'estado',
                'fk_zona_actual',
                'fk_licencia_requerida'
            ])
            ->get()
            ->map(fn($u) => (array)$u)
            ->toArray();

        // Zonas vecinas
        $zonas_vecinas = DB::table('zona_vecina')
            ->select(['fk_zona', 'fk_zona_vecina'])
            ->get()
            ->map(fn($z) => (array)$z)
            ->toArray();

        // Certificaciones por operador
        $certificacionesOperador = DB::table('operador_certificacion')
            ->select(['fk_operador', 'fk_certificacion'])
            ->get()
            ->map(fn($c) => (array)$c)
            ->toArray();

        // Cuotas del periodo
        $cuotas = DB::table('operador_cuota')
            ->select(['fk_operador', 'cuota_objetivo', 'cuota_realizada'])
            ->where('periodo', $periodo)
            ->get()
            ->map(fn($c) => (array)$c)
            ->toArray();

        // Últimos viajes por operador
        $ultimos_viajes = DB::table('viaje')
            ->selectRaw('fk_operador, MAX(fecha_salida) as ultimo_viaje')
            ->whereNotNull('fk_operador')
            ->groupBy('fk_operador')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        // ✅ Certificaciones requeridas del viaje (multi)
        $certIds = $viaje->certificaciones
            ? $viaje->certificaciones->pluck('id_certificacion')->map(fn($x) => (int)$x)->values()->all()
            : [];

        // Payload del viaje (lo que consume el motor)
        $viajePayload = [
            'id_viaje' => (int)$viaje->id_viaje,
            'origen_zona_id' => (int)$viaje->ruta->fk_zona_origen,
            'certificaciones_requeridas' => $certIds, // ✅ multi
            'fk_licencia_requerida' => $viaje->fk_licencia_requerida,
            'tarifa_operador' => (float)$viaje->ruta->tarifa_operador,
        ];

        $payload = [
            'periodo' => $periodo,
            'viaje' => $viajePayload,
            'operadores' => $operadores,
            'unidades' => $unidades,
            'zonas_vecinas' => $zonas_vecinas,
            'certificaciones' => $certificacionesOperador,
            'cuotas' => $cuotas,
            'ultimos_viajes' => $ultimos_viajes,
            'rechazados' => $rechazados,
        ];

        $resp = Http::timeout(20)->post('http://127.0.0.1:8001/asignar', $payload);

        if (!$resp->ok()) {
            return response()->json([
                'ok' => false,
                'msg' => 'Error en motor Python',
                'detail' => $resp->body(),
            ], 500);
        }

        $decision = $resp->json();
        $top = $decision['top'] ?? [];

        $opsIds = collect($top)->pluck('id_operador')->filter()->unique()->values()->all();
        $uniIds = collect($top)->pluck('unidad_id')->filter()->unique()->values()->all();

        $opsMap = DB::table('operador')
            ->whereIn('id_operador', $opsIds)
            ->select('id_operador', 'numero_empleado', 'nombres', 'apellidos')
            ->get()
            ->keyBy('id_operador');

        $uniMap = DB::table('unidad')
            ->whereIn('id_unidad', $uniIds)
            ->select('id_unidad', 'numero_economico', 'estado', 'fk_zona_actual')
            ->get()
            ->keyBy('id_unidad');

        $rankingMap = collect($decision['ranking'] ?? [])->keyBy('id_operador');

        $topEnriched = array_map(function ($r) use ($opsMap, $uniMap, $rankingMap, $periodo, $viajePayload) {
            $op = $opsMap->get($r['id_operador']);
            $un = isset($r['unidad_id']) ? $uniMap->get($r['unidad_id']) : null;

            $rk = $rankingMap->get($r['id_operador']);
            $motivos = $r['motivos'] ?? [];
            if (empty($motivos) && $rk && isset($rk->motivos) && !empty($rk->motivos)) {
                $motivos = $rk->motivos;
            }

            $razones = [];
            $razones[] = "Periodo: {$periodo}";
            $razones[] = "Cuota restante: " . ($r['cuota_restante'] ?? 'N/A');
            $razones[] = "Días sin viaje: " . ($r['dias_sin_viaje'] ?? 'N/A');
            $razones[] = "Penalización: " . ($r['penalizacion'] ?? 0);

            if (!empty($viajePayload['fk_licencia_requerida'])) {
                $razones[] = "Licencia requerida del viaje (ID: {$viajePayload['fk_licencia_requerida']})";
            } else {
                $razones[] = "Licencia requerida: No requerida";
            }

            $certsReq = $viajePayload['certificaciones_requeridas'] ?? [];
            if (!empty($certsReq)) {
                $razones[] = "Certificaciones requeridas del viaje (IDs: " . implode(', ', $certsReq) . ")";
            } else {
                $razones[] = "Certificaciones requeridas: No requerida";
            }

            if (!empty($r['rechazado'])) {
                $razones[] = "⚠️ Rechazado previamente para este viaje";
            }

            return [
                'id_operador' => $r['id_operador'],
                'operador' => $op ? [
                    'numero_empleado' => $op->numero_empleado,
                    'nombre' => trim(($op->nombres ?? '') . ' ' . ($op->apellidos ?? '')),
                ] : null,

                'unidad_id' => $r['unidad_id'] ?? null,
                'unidad' => $un ? [
                    'numero_economico' => $un->numero_economico,
                    'estado' => $un->estado,
                    'fk_zona_actual' => $un->fk_zona_actual,
                ] : null,

                'cuota_restante' => $r['cuota_restante'] ?? null,
                'dias_sin_viaje' => $r['dias_sin_viaje'] ?? null,
                'penalizacion' => $r['penalizacion'] ?? 0,
                'rechazado' => (bool)($r['rechazado'] ?? false),

                'motivos' => $motivos,
                'razones' => $razones,
            ];
        }, $top);

        return response()->json([
            'ok' => (bool)($decision['ok'] ?? true),
            'motivo' => $decision['motivo'] ?? null,
            'top' => $topEnriched,
            'ranking' => $decision['ranking'] ?? [],
            'rechazados' => $rechazados,
        ], 200);
    }

    // ✅ APROBAR = asignación final (viaje + estados + cuota)
    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'operadorId' => 'required|integer',
        ]);

        $id_viaje = (int) $id;
        $id_operador = (int) $request->input('operadorId');
        $periodo = DB::table('operador_cuota')->max('periodo') ?? now()->format('Ym');

        try {
            return DB::transaction(function () use ($id_viaje, $id_operador, $periodo) {

                $viaje = Viaje::with('ruta')
                    ->where('id_viaje', $id_viaje)
                    ->lockForUpdate()
                    ->first();

                if (!$viaje) {
                    return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
                }

                if (($viaje->estado ?? '') !== 'PENDIENTE') {
                    return response()->json(['ok' => false, 'msg' => 'El viaje no está en estado PENDIENTE'], 409);
                }

                if (!$viaje->ruta) {
                    return response()->json(['ok' => false, 'msg' => 'Ruta no encontrada para el viaje'], 404);
                }

                $operador = DB::table('operador')
                    ->where('id_operador', $id_operador)
                    ->lockForUpdate()
                    ->first();

                if (!$operador) {
                    return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
                }

                if (($operador->estado_operador ?? '') !== 'DISPONIBLE') {
                    return response()->json(['ok' => false, 'msg' => 'El operador no está disponible'], 409);
                }

                $id_unidad = $operador->fk_unidad_asignada ?? null;
                if (!$id_unidad) {
                    return response()->json(['ok' => false, 'msg' => 'El operador no tiene unidad asignada'], 422);
                }

                $unidad = DB::table('unidad')
                    ->where('id_unidad', $id_unidad)
                    ->lockForUpdate()
                    ->first();

                if (!$unidad) {
                    return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
                }

                if (($unidad->estado ?? '') !== 'DISPONIBLE') {
                    return response()->json(['ok' => false, 'msg' => 'La unidad no está disponible'], 409);
                }

                $monto = $viaje->pago_operador ?? $viaje->ruta->tarifa_operador;
                $monto = (float) ($monto ?? 0);

                if ($monto <= 0) {
                    return response()->json(['ok' => false, 'msg' => 'El viaje no tiene pago válido (>0)'], 422);
                }

                $viaje->update([
                    'fk_operador'   => $id_operador,
                    'fk_unidad'     => $id_unidad,
                    'estado'        => 'ASIGNADO',
                    'fecha_salida'  => now(),
                    'pago_operador' => $monto,
                ]);

                DB::table('operador')->where('id_operador', $id_operador)->update(['estado_operador' => 'NO_DISPONIBLE']);
                DB::table('unidad')->where('id_unidad', $id_unidad)->update(['estado' => 'NO_DISPONIBLE']);

                $cuota = DB::table('operador_cuota')
                    ->where('fk_operador', $id_operador)
                    ->where('periodo', $periodo)
                    ->lockForUpdate()
                    ->first();

                if (!$cuota) {
                    return response()->json(['ok' => false, 'msg' => "No hay cuota cargada para periodo {$periodo}"], 422);
                }

                DB::table('operador_cuota')
                    ->where('fk_operador', $id_operador)
                    ->where('periodo', $periodo)
                    ->update([
                        'cuota_realizada' => (float) $cuota->cuota_realizada + $monto
                    ]);

                return response()->json([
                    'ok' => true,
                    'msg' => 'Viaje aprobado/asignado correctamente',
                    'data' => [
                        'id_viaje' => $viaje->id_viaje,
                        'numero_viaje' => $viaje->numero_viaje,
                        'id_operador' => $id_operador,
                        'id_unidad' => $id_unidad,
                        'monto' => $monto,
                        'periodo' => $periodo,
                        'estado_viaje' => 'ASIGNADO',
                        'estado_operador' => 'NO_DISPONIBLE',
                        'estado_unidad' => 'NO_DISPONIBLE',
                    ]
                ], 200);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'msg' => 'Error al aprobar el viaje',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function rechazar(Request $request, $id)
    {
        $request->validate([
            'operadorId' => 'required|integer',
            'motivos'    => 'required|string',
        ]);

        $id_viaje = (int) $id;
        $id_operador = (int) $request->input('operadorId');
        $motivoTexto = trim((string) $request->input('motivos'));

        try {
            $viaje = DB::table('viaje')->where('id_viaje', $id_viaje)->first();
            if (!$viaje) return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);

            $op = DB::table('operador')->where('id_operador', $id_operador)->first();
            if (!$op) return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);

            $motivosJson = json_encode(['motivo' => $motivoTexto], JSON_UNESCAPED_UNICODE);

            DB::table('rechazos_operador')->insert([
                'fk_viaje'    => $id_viaje,
                'fk_operador' => $id_operador,
                'motivos'     => $motivosJson,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return response()->json(['ok' => true, 'msg' => 'Rechazo registrado correctamente'], 200);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => 'Error al registrar el rechazo', 'detail' => $e->getMessage()], 500);
        }
    }

    public function reasignar($id, Request $request)
    {
        $viaje = Viaje::find($id);

        if (!$viaje) return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);

        if (!in_array($viaje->estado, ['ASIGNADO', 'EN_CURSO'])) {
            return response()->json(['ok' => false, 'msg' => 'Solo se puede reasignar viajes asignados o en curso'], 400);
        }

        $nuevoOperador = $request->input('fk_operador');
        $nuevaUnidad = $request->input('fk_unidad');

        $operadorValido = Operador::find($nuevoOperador);
        $unidadValida = Unidad::find($nuevaUnidad);

        if (!$operadorValido) return response()->json(['ok' => false, 'msg' => 'Operador no válido'], 422);
        if (!$unidadValida) return response()->json(['ok' => false, 'msg' => 'Unidad no válida'], 422);

        $viaje->update([
            'fk_operador' => $nuevoOperador,
            'fk_unidad' => $nuevaUnidad,
            'estado' => 'EN_CURSO',
            'fecha_salida' => now(),
        ]);

        $viaje->historialReasignaciones()->create([
            'fk_operador_anterior' => $viaje->fk_operador,
            'fk_unidad_anterior' => $viaje->fk_unidad,
            'fk_operador_nuevo' => $nuevoOperador,
            'fk_unidad_nueva' => $nuevaUnidad,
            'motivos' => 'Reasignación por coordinador',
        ]);

        return response()->json(['ok' => true, 'msg' => 'Viaje reasignado correctamente']);
    }
}