<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Viaje;
use App\Models\Operador;
use App\Models\Unidad;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Ruta;
use App\Models\Certificacion;
use App\Models\ViajeRechazado;
use App\Models\EdicionesTarifaViaje;
use Illuminate\Support\Facades\Log;
use App\Models\ViajeIncidencia;
use Illuminate\Support\Facades\Auth;
use App\Models\OperadorMovimiento;
use App\Models\OperadorHistorialEstado;
use App\Models\OperadorHistorialZona;
use App\Models\UnidadHistorialZona;
use App\Models\ViajeFinalizacion;
use Carbon\Carbon;

class ViajeController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // HELPER PRIVADO — Buscar cuota para un viaje
    // ══════════════════════════════════════════════════════════════════════
    //
    // Problema: un viaje puede asignarse un domingo (cuota vigente) y
    // operarse/cancelarse/reasignarse el lunes (cuota ya vencida).
    // Con la búsqueda original por "hoy" no se encuentra la cuota y
    // el movimiento no se registra ni se actualiza la cuota.
    //
    // Solución: intentar primero con hoy, y si no hay resultado,
    // buscar la cuota que cubría la fecha_salida del viaje.
    // Esto permite que operaciones sobre viajes que cruzan el límite
    // del periodo afecten correctamente la cuota correspondiente.
    //
    // Nota: se usa lockForUpdate() para evitar condiciones de carrera
    // cuando múltiples requests tocan la misma cuota simultáneamente.
    //
    private function buscarCuota(int $idOperador, Viaje $viaje): ?object
    {
        $hoy = now()->toDateString();

        // 1. Intento normal: cuota vigente hoy
        $cuota = DB::table('operador_cuota')
            ->where('fk_operador', $idOperador)
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin',    '>=', $hoy)
            ->lockForUpdate()
            ->first();

        if ($cuota) return $cuota;

        // 2. Fallback: cuota que cubría la fecha_salida del viaje
        // Cubre el caso: asignado domingo (dentro del periodo),
        // cancelado/reasignado/tarifa-modificada el lunes (periodo ya vencido).
        $fechaReferencia = $viaje->fecha_salida
            ? Carbon::parse($viaje->fecha_salida)->toDateString()
            : null;

        if (!$fechaReferencia) return null;

        return DB::table('operador_cuota')
            ->where('fk_operador', $idOperador)
            ->where('fecha_inicio', '<=', $fechaReferencia)
            ->where('fecha_fin',    '>=', $fechaReferencia)
            ->lockForUpdate()
            ->first();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Sin cambios: obtenerViajesPendientes, calcularAsignacion, aprobar,
    // iniciarViaje, rechazar, historialViaje, historialEstadosOperador,
    // historialZonasOperador, historialZonasUnidad, obtenerCadena,
    // getFinalizacion, finalizar
    // ══════════════════════════════════════════════════════════════════════

    public function obtenerViajesPendientes()
    {
        $rows = DB::table('viaje as v')
            ->join('ruta as r', 'r.id_ruta', '=', 'v.fk_ruta')
            ->leftJoin('licencia as l', 'l.id_licencia', '=', 'v.fk_licencia_requerida')
            ->leftJoin('viaje_certificacion as vc', 'vc.fk_viaje', '=', 'v.id_viaje')
            ->leftJoin('certificacion as c', 'c.id_certificacion', '=', 'vc.fk_certificacion')
            ->select(
                'v.id_viaje', 'v.numero_viaje', 'v.estado', 'v.configuracion_unidad',
                'v.fk_ruta', 'r.nombre_ruta', 'r.fk_zona_origen as origen_zona_id',
                'r.fk_zona_destino', 'r.distancia_km',
                'v.fk_licencia_requerida', 'l.nombre_licencia',
                'c.id_certificacion as cert_id', 'c.nombre_certificacion as cert_nombre',
                'v.pago_operador'
            )
            ->where('v.estado', 'PENDIENTE')
            ->orderBy('v.id_viaje', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['ok' => false, 'msg' => 'No hay viajes pendientes'], 404);
        }

        $viajes = $rows->groupBy('id_viaje')->map(function ($items) {
            $first = $items->first();
            $certs = $items->filter(fn($x) => !is_null($x->cert_id))
                ->map(fn($x) => [
                    'id_certificacion'     => (int)$x->cert_id,
                    'nombre_certificacion' => $x->cert_nombre,
                ])
                ->unique('id_certificacion')->values();

            return [
                'id_viaje'              => (int)$first->id_viaje,
                'numero_viaje'          => $first->numero_viaje,
                'estado'                => $first->estado,
                'fk_ruta'               => (int)$first->fk_ruta,
                'nombre_ruta'           => $first->nombre_ruta,
                'origen_zona_id'        => (int)$first->origen_zona_id,
                'fk_zona_destino'       => (int)$first->fk_zona_destino,
                'distancia_km'          => (float)$first->distancia_km,
                'fk_licencia_requerida' => $first->fk_licencia_requerida ? (int)$first->fk_licencia_requerida : null,
                'nombre_licencia'       => $first->nombre_licencia ?? null,
                'certificaciones'       => $certs,
                'pago_operador'         => $first->pago_operador !== null ? (float)$first->pago_operador : null,
            ];
        })->values();

        return response()->json(['ok' => true, 'viajes' => $viajes], 200);
    }

    public function calcularAsignacion($id_viaje)
    {
        $viaje = Viaje::with(['ruta', 'certificaciones'])->where('id_viaje', $id_viaje)->first();

        if (!$viaje || !$viaje->ruta) {
            return response()->json(['ok' => false, 'msg' => 'Viaje o ruta no encontrada'], 404);
        }

        $hoy    = now()->toDateString();
        $periodo = now()->format('Ym');

        $rechazados = DB::table('rechazos_operador')
            ->where('fk_viaje', (int)$id_viaje)
            ->pluck('fk_operador')
            ->map(fn($x) => (int)$x)
            ->unique()->values()->all();

        $operadores = DB::table('operador')
            ->select(['id_operador', 'estado_operador', 'fk_zona_actual', 'vigencia_licencia', 'fk_unidad_asignada', 'fk_tipo_licencia'])
            ->get()->map(fn($o) => (array)$o)->toArray();

        $unidades = DB::table('unidad')
            ->select(['id_unidad', 'numero_economico', 'estado', 'fk_zona_actual', 'fk_licencia_requerida'])
            ->get()->map(fn($u) => (array)$u)->toArray();

        $zonas_vecinas = DB::table('zona_vecina')
            ->select(['fk_zona', 'fk_zona_vecina'])
            ->get()->map(fn($z) => (array)$z)->toArray();

        $certificacionesOperador = DB::table('operador_certificacion')
            ->select(['fk_operador', 'fk_certificacion'])
            ->get()->map(fn($c) => (array)$c)->toArray();

        $cuotas = DB::table('operador_cuota')
            ->select(['fk_operador', 'cuota_objetivo', 'cuota_realizada'])
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin',    '>=', $hoy)
            ->get()->map(fn($c) => (array)$c)->toArray();

        $ultimos_viajes = DB::table('viaje')
            ->selectRaw('fk_operador, MAX(fecha_salida) as ultimo_viaje')
            ->whereNotNull('fk_operador')
            ->groupBy('fk_operador')
            ->get()->map(fn($r) => (array)$r)->toArray();

        $viajes_activos_por_operador = DB::table('viaje as v')
            ->join('ruta as r', 'r.id_ruta', '=', 'v.fk_ruta')
            ->whereIn('v.estado', ['EN_CURSO'])
            ->whereNotNull('v.fk_operador')
            ->select([
                'v.fk_operador as id_operador',
                'v.id_viaje    as id_viaje_activo',
                'r.fk_zona_destino as zona_destino_viaje',
                'r.nombre_ruta      as nombre_ruta_activa',
            ])
            ->get()->map(fn($r) => (array)$r)->toArray();

        $eslabonesActuales = DB::table('viaje_encadenamiento as ve')
            ->join('viaje as v', 'v.id_viaje', '=', 've.fk_viaje_hijo')
            ->whereIn('v.estado', ['PENDIENTE', 'ASIGNADO', 'EN_CURSO'])
            ->select('ve.fk_operador', DB::raw('COUNT(*) as total_eslabones'))
            ->groupBy('ve.fk_operador')
            ->get()
            ->keyBy('fk_operador');

        $operadoresConEncadenamientoActivo = DB::table('viaje_encadenamiento as ve')
            ->join('viaje as v', 'v.id_viaje', '=', 've.fk_viaje_hijo')
            ->whereIn('v.estado', ['PENDIENTE', 'ASIGNADO'])
            ->select(
                've.fk_operador',
                'v.numero_viaje as numero_viaje_encadenado',
                'v.id_viaje     as id_viaje_encadenado'
            )
            ->get()
            ->keyBy('fk_operador');

        $certIds = $viaje->certificaciones
            ? $viaje->certificaciones->pluck('id_certificacion')->map(fn($x) => (int)$x)->values()->all()
            : [];

        $viajePayload = [
            'id_viaje'                   => (int)$viaje->id_viaje,
            'origen_zona_id'             => (int)$viaje->ruta->fk_zona_origen,
            'certificaciones_requeridas' => $certIds,
            'fk_licencia_requerida'      => $viaje->fk_licencia_requerida,
            'tarifa_operador'            => (float)$viaje->ruta->pago_operador,
        ];

        $payload = [
            'periodo'                     => $periodo,
            'viaje'                       => $viajePayload,
            'operadores'                  => $operadores,
            'unidades'                    => $unidades,
            'zonas_vecinas'               => $zonas_vecinas,
            'certificaciones'             => $certificacionesOperador,
            'cuotas'                      => $cuotas,
            'ultimos_viajes'              => $ultimos_viajes,
            'rechazados'                  => $rechazados,
            'viajes_activos_por_operador' => $viajes_activos_por_operador,
        ];

        $resp = Http::timeout(20)->post('http://127.0.0.1:8001/asignar', $payload);

        if (!$resp->ok()) {
            return response()->json(['ok' => false, 'msg' => 'Error en motor Python', 'detail' => $resp->body()], 500);
        }

        $decision = $resp->json();
        $ranking  = $decision['ranking'] ?? [];

        $opsIds = collect($ranking)->pluck('id_operador')->filter()->unique()->values()->all();
        $uniIds = collect($ranking)->pluck('unidad_id')->filter()->unique()->values()->all();

        $zonasMap           = DB::table('zona')->select(['id_zona', 'nombre_zona'])->get()->keyBy('id_zona');
        $licenciasMap       = DB::table('licencia')->select(['id_licencia', 'nombre_licencia'])->get()->keyBy('id_licencia');
        $certificacionesMap = DB::table('certificacion')->select(['id_certificacion', 'nombre_certificacion'])->get()->keyBy('id_certificacion');

        $opsMap = DB::table('operador')
            ->whereIn('id_operador', $opsIds)
            ->select(['id_operador', 'numero_empleado', 'nombres', 'apellidos', 'estado_operador', 'fk_zona_actual', 'vigencia_licencia', 'fk_tipo_licencia'])
            ->get()->keyBy('id_operador');

        $uniMap = DB::table('unidad')
            ->whereIn('id_unidad', $uniIds)
            ->select(['id_unidad', 'numero_economico', 'estado', 'fk_zona_actual'])
            ->get()->keyBy('id_unidad');

        $viajesActivosMap = collect($viajes_activos_por_operador)->keyBy('id_operador');

        $origenZonaId      = $viajePayload['origen_zona_id'];
        $licenciaRequerida = $viajePayload['fk_licencia_requerida'];

        $zonasOk = collect($zonas_vecinas)
            ->filter(fn($z) => (int)$z['fk_zona'] === $origenZonaId)
            ->pluck('fk_zona_vecina')
            ->push($origenZonaId)
            ->unique()->all();

        $rankingEnriched = array_map(
            function ($r) use (
                $opsMap, $uniMap, $origenZonaId, $licenciaRequerida,
                $zonasOk, $zonasMap, $licenciasMap, $certificacionesMap,
                $viajesActivosMap, $eslabonesActuales,
                $operadoresConEncadenamientoActivo
            ) {
                $op = $opsMap->get($r['id_operador']);
                $un = isset($r['unidad_id']) ? $uniMap->get($r['unidad_id']) : null;

                $zonaOperador      = $op?->fk_zona_actual ?? null;
                $operadorFueraZona = !in_array($zonaOperador, $zonasOk);
                $vigencia          = $op?->vigencia_licencia ?? null;
                $licenciaVencida   = $vigencia ? (strtotime($vigencia) < time()) : true;
                $unidadAsignada    = $un !== null;
                $zonaUnidad        = $un?->fk_zona_actual ?? null;
                $unidadFueraZona   = !in_array($zonaUnidad, $zonasOk);
                $cumpleLicencia    = $licenciaRequerida === null
                    ? true
                    : ((int)($op?->fk_tipo_licencia ?? -1) === (int)$licenciaRequerida);

                $certsCumplidas = collect($r['certificaciones_cumplidas'] ?? [])
                    ->map(fn($id) => $certificacionesMap->get($id)?->nombre_certificacion ?? "Cert. ID: $id")
                    ->values()->toArray();

                $certsFaltantes = collect($r['certificaciones_faltantes'] ?? [])
                    ->map(fn($id) => $certificacionesMap->get($id)?->nombre_certificacion ?? "Cert. ID: $id")
                    ->values()->toArray();

                $opId           = $r['id_operador'];
                $esEncadenable  = (bool)($r['es_encadenable'] ?? false);
                $viajeActivo    = $viajesActivosMap->get($opId);
                $totalEslabones = (int)($eslabonesActuales->get($opId)?->total_eslabones ?? 0);

                $encadenamientoExistente  = $operadoresConEncadenamientoActivo->get($opId);
                $tieneEncadenadoPendiente = $encadenamientoExistente !== null;

                $puedeEncadenar = $esEncadenable
                    && $totalEslabones < 3
                    && !$tieneEncadenadoPendiente;

                return [
                    'id_operador'    => $opId,
                    'cuota_restante' => $r['cuota_restante'] ?? 0,
                    'penalizacion'   => $r['penalizacion'] ?? 0,
                    'rechazado'      => (bool)($r['rechazado'] ?? false),
                    'motivos'        => $r['motivos'] ?? [],
                    'operador' => $op ? [
                        'id_operador'       => (int)$op->id_operador,
                        'nombre'            => trim(($op->nombres ?? '') . ' ' . ($op->apellidos ?? '')),
                        'numero_empleado'   => $op->numero_empleado,
                        'estado_operador'   => $op->estado_operador,
                        'nombre_zona'       => $zonasMap->get($op->fk_zona_actual)?->nombre_zona ?? 'N/A',
                        'fk_zona_actual'    => $op->fk_zona_actual,
                        'vigencia_licencia' => $op->vigencia_licencia,
                        'fk_tipo_licencia'  => $op->fk_tipo_licencia,
                        'nombre_licencia'   => $licenciasMap->get($op->fk_tipo_licencia)?->nombre_licencia ?? 'N/A',
                    ] : null,
                    'unidad' => $un ? [
                        'id_unidad'        => (int)$un->id_unidad,
                        'numero_economico'  => $un->numero_economico,
                        'estado_unidad'     => $un->estado,
                        'fk_zona_actual'    => $un->fk_zona_actual,
                        'nombre_zona'       => $zonasMap->get($un->fk_zona_actual)?->nombre_zona ?? 'N/A',
                    ] : null,
                    'operador_disponible'              => in_array($op?->estado_operador ?? '', ['DISPONIBLE', 'ACTIVO']),
                    'operador_fuera_zona'              => $operadorFueraZona,
                    'licencia_vencida'                 => $licenciaVencida,
                    'unidad_asignada'                  => $unidadAsignada,
                    'unidad_fuera_zona'                => $unidadFueraZona,
                    'cumple_licencia'                  => $cumpleLicencia,
                    'certificaciones_cumplidas'        => $certsCumplidas,
                    'certificaciones_faltantes'        => $certsFaltantes,
                    'es_encadenable'                   => $puedeEncadenar,
                    'viaje_activo_id'                  => $viajeActivo['id_viaje_activo'] ?? null,
                    'zona_efectiva_nombre'             => $zonasMap->get($r['zona_efectiva'] ?? null)?->nombre_zona ?? null,
                    'total_eslabones'                  => $totalEslabones,
                    'ruta_activa'                      => $r['ruta_activa'] ?? null,
                    'lejos'                            => (bool)($r['lejos'] ?? false),
                    'bloqueado'                        => (bool)($r['bloqueado'] ?? false),
                    'tiene_viaje_encadenado_pendiente' => $tieneEncadenadoPendiente,
                    'numero_viaje_encadenado_pendiente'=> $encadenamientoExistente?->numero_viaje_encadenado ?? null,
                    'id_viaje_encadenado_pendiente'    => $encadenamientoExistente?->id_viaje_encadenado ?? null,
                ];
            },
            $ranking
        );

        return response()->json([
            'ok'         => (bool)($decision['ok'] ?? true),
            'motivo'     => $decision['motivo'] ?? null,
            'ranking'    => $rankingEnriched,
            'rechazados' => $rechazados,
        ], 200);
    }

    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'operadorId'     => 'required|integer',
            'advertencias'   => 'nullable|array',
            'id_viaje_padre' => 'nullable|integer|exists:viaje,id_viaje',
            'ranking_info'   => 'nullable|array',
        ]);

        $id_viaje       = (int) $id;
        $id_operador    = (int) $request->input('operadorId');
        $advertencias   = $request->input('advertencias', []);
        $id_viaje_padre = $request->input('id_viaje_padre');
        $ranking_info   = $request->input('ranking_info', []);

        try {
            return DB::transaction(function () use ($id_viaje, $id_operador, $advertencias, $id_viaje_padre, $ranking_info) {

                $viaje    = Viaje::find($id_viaje);
                $operador = Operador::find($id_operador);

                if (!$viaje || !$operador) {
                    return response()->json(['ok' => false, 'msg' => 'Viaje o operador no encontrado'], 404);
                }

                $estadoValido = $operador->estado_operador === 'DISPONIBLE'
                    || ($id_viaje_padre && $operador->estado_operador === 'EN_VIAJE');

                if (!$estadoValido) {
                    $msg = $operador->estado_operador === 'ASIGNADO'
                        ? 'El operador tiene un viaje asignado pero no iniciado. Solo se puede encadenar a operadores con viaje EN_CURSO.'
                        : 'El operador no está en un estado válido para asignación.';
                    return response()->json(['ok' => false, 'msg' => $msg], 409);
                }

                if ($id_viaje_padre) {
                    $yaEncadenado = DB::table('viaje_encadenamiento')
                        ->where('fk_viaje_hijo', $id_viaje)
                        ->exists();

                    if ($yaEncadenado) {
                        return response()->json(['ok' => false, 'msg' => 'Este viaje ya está encadenado a otro viaje.'], 422);
                    }

                    $viajePadreObj = Viaje::find($id_viaje_padre);
                    if (!$viajePadreObj || $viajePadreObj->estado !== 'EN_CURSO') {
                        return response()->json(['ok' => false, 'msg' => 'El viaje padre debe estar EN_CURSO para poder encadenar.'], 422);
                    }

                    $encadenamientoActivo = DB::table('viaje_encadenamiento as ve')
                        ->join('viaje as v', 'v.id_viaje', '=', 've.fk_viaje_hijo')
                        ->whereIn('v.estado', ['PENDIENTE', 'ASIGNADO'])
                        ->where('ve.fk_operador', $id_operador)
                        ->where('ve.fk_viaje_padre', '!=', $id_viaje_padre)
                        ->select('v.numero_viaje', 'v.estado')
                        ->first();

                    if ($encadenamientoActivo) {
                        return response()->json([
                            'ok'  => false,
                            'msg' => "El operador ya tiene un viaje encadenado activo "
                                   . "(#{$encadenamientoActivo->numero_viaje} - {$encadenamientoActivo->estado}). "
                                   . "Solo se permite un encadenamiento activo por operador.",
                        ], 422);
                    }
                }

                $id_unidad = $operador->fk_unidad_asignada;
                if (!$id_unidad) {
                    return response()->json(['ok' => false, 'msg' => 'El operador no tiene unidad asignada'], 422);
                }

                $unidad = Unidad::find($id_unidad);
                if (!$unidad) {
                    return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
                }

                $viaje->update([
                    'fk_operador'    => $id_operador,
                    'fk_unidad'      => $id_unidad,
                    'estado'         => 'ASIGNADO',
                    'pago_operador'  => $viaje->pago_operador,
                    'fk_viaje_padre' => $id_viaje_padre ?? null,
                ]);

                if ($operador->estado_operador === 'DISPONIBLE') {
                    OperadorHistorialEstado::create([
                        'fk_operador'     => $id_operador,
                        'fk_coordinador'  => Auth::id(),
                        'estado_anterior' => $operador->estado_operador,
                        'estado_nuevo'    => 'ASIGNADO',
                        'motivo'          => "Asignación al viaje #{$viaje->numero_viaje}",
                        'created_at'      => now(),
                    ]);
                    DB::table('operador')->where('id_operador', $id_operador)
                        ->update(['estado_operador' => 'ASIGNADO']);

                    DB::table('reporte_unidad')->insert([
                        'fk_unidad'       => $id_unidad,
                        'estado_anterior' => $unidad->estado,
                        'estado_nuevo'    => 'ASIGNADA_A_VIAJE',
                        'motivo'          => "Asignación al viaje #{$viaje->numero_viaje}",
                        'fecha_reporte'   => now(),
                    ]);
                    DB::table('unidad')->where('id_unidad', $id_unidad)
                        ->update(['estado' => 'ASIGNADA_A_VIAJE']);
                }

                $monto = (float)($viaje->pago_operador ?? optional($viaje->ruta)->tarifa_operador ?? 0);
                if ($monto <= 0) {
                    return response()->json(['ok' => false, 'msg' => 'El viaje no tiene pago válido (>0)'], 422);
                }

                $hoy         = now()->toDateString();
                $cuotaActiva = DB::table('operador_cuota')
                    ->where('fk_operador', $id_operador)
                    ->where('fecha_inicio', '<=', $hoy)
                    ->where('fecha_fin',    '>=', $hoy)
                    ->first();

                if (!$cuotaActiva) {
                    return response()->json([
                        'ok'  => false,
                        'msg' => 'El operador no tiene una cuota activa para la semana actual.',
                    ], 422);
                }

                DB::table('operador_cuota')
                    ->where('id_op_cuota', $cuotaActiva->id_op_cuota)
                    ->update(['cuota_realizada' => DB::raw('cuota_realizada + ' . $monto)]);

                OperadorMovimiento::create([
                    'fk_operador'    => $id_operador,
                    'fk_viaje'       => $id_viaje,
                    'fk_coordinador' => Auth::id(),
                    'periodo'        => $cuotaActiva->periodo,
                    'tipo'           => 'ASIGNACION',
                    'monto'          => $monto,
                    'descripcion'    => "Asignación al viaje #{$viaje->numero_viaje}",
                    'created_at'     => now(),
                ]);

                if ($id_viaje_padre) {
                    $orden = DB::table('viaje_encadenamiento')
                        ->where('fk_viaje_padre', $id_viaje_padre)
                        ->count() + 1;

                    DB::table('viaje_encadenamiento')->insert([
                        'fk_viaje_padre' => $id_viaje_padre,
                        'fk_viaje_hijo'  => $id_viaje,
                        'orden'          => $orden,
                        'fk_operador'    => $id_operador,
                        'fk_coordinador' => Auth::id(),
                        'created_at'     => now(),
                    ]);

                    ViajeIncidencia::create([
                        'fk_viaje'       => $id_viaje,
                        'fk_operador'    => $id_operador,
                        'fk_coordinador' => Auth::id(),
                        'tipo_evento'    => 'ENCADENAMIENTO_ASIGNADO',
                        'detalle'        => json_encode(['viaje_padre' => $id_viaje_padre, 'orden' => $orden]),
                        'created_at'     => now(),
                    ]);
                }

                $posElegido  = (int)($ranking_info['pos_elegido'] ?? 1);
                $nombreMejor = $ranking_info['nombre_mejor'] ?? null;

                ViajeIncidencia::create([
                    'fk_viaje'       => $id_viaje,
                    'fk_operador'    => $id_operador,
                    'fk_coordinador' => Auth::id(),
                    'tipo_evento'    => 'ENCADENAMIENTO_RANKING',
                    'detalle'        => json_encode(
                        $posElegido === 1
                            ? ['resultado' => 'mejor_opcion', 'pos_elegido' => 1, 'mensaje' => 'Se asignó al operador con mejor ranking.']
                            : ['resultado' => 'no_mejor_opcion', 'pos_elegido' => $posElegido,
                               'mejor_operador' => $nombreMejor, 'pos_mejor' => (int)($ranking_info['pos_mejor'] ?? 1),
                               'mensaje' => "Se eligió la posición #{$posElegido}. El mejor operador era: {$nombreMejor}."]
                    ),
                    'created_at'     => now(),
                ]);

                $hayAdvertencias = !empty($advertencias);
                ViajeIncidencia::create([
                    'fk_viaje'                      => $id_viaje,
                    'fk_operador'                   => $id_operador,
                    'fk_coordinador'                => Auth::id(),
                    'tipo_evento'                   => $hayAdvertencias ? 'ASIGNACION_CON_ADVERTENCIAS' : 'ASIGNACION_OK',
                    'adv_unidad_no_disponible'      => in_array('unidad_no_disponible',      $advertencias),
                    'adv_licencia_vencida'          => in_array('licencia_vencida',          $advertencias),
                    'adv_licencia_incorrecta'       => in_array('licencia_incorrecta',       $advertencias),
                    'adv_operador_fuera_zona'       => in_array('operador_fuera_zona',       $advertencias),
                    'adv_unidad_fuera_zona'         => in_array('unidad_fuera_zona',         $advertencias),
                    'adv_cuota_agotada'             => in_array('cuota_agotada',             $advertencias),
                    'adv_certificaciones_faltantes' => in_array('certificaciones_faltantes', $advertencias),
                    'detalle'                       => $hayAdvertencias ? json_encode($advertencias) : null,
                    'created_at'                    => now(),
                ]);

                return response()->json([
                    'ok'  => true,
                    'msg' => 'Viaje aprobado correctamente',
                    'data' => [
                        'id_viaje'        => $viaje->id_viaje,
                        'numero_viaje'    => $viaje->numero_viaje,
                        'id_operador'     => $id_operador,
                        'id_unidad'       => $id_unidad,
                        'estado_viaje'    => 'ASIGNADO',
                        'estado_operador' => $operador->estado_operador,
                        'estado_unidad'   => $unidad->estado,
                        'es_encadenado'   => !is_null($id_viaje_padre),
                        'viaje_padre'     => $id_viaje_padre,
                    ],
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Error en aprobar viaje', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error al aprobar el viaje', 'detail' => $e->getMessage()], 500);
        }
    }

    public function iniciarViaje(Request $request, $id)
    {
        $request->validate(['fecha_inicio' => 'required|date']);

        $viaje = Viaje::find($id);
        if (!$viaje) {
            return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
        }
        if ($viaje->estado !== 'ASIGNADO') {
            return response()->json(['ok' => false, 'msg' => 'Solo se pueden iniciar viajes en estado ASIGNADO.'], 409);
        }

        if ($viaje->fk_viaje_padre) {
            $padre = Viaje::find($viaje->fk_viaje_padre);
            if ($padre && $padre->estado !== 'TERMINADO') {
                return response()->json([
                    'ok'  => false,
                    'msg' => "El viaje padre #{$padre->numero_viaje} debe estar TERMINADO antes de iniciar este viaje encadenado.",
                    'viaje_padre' => [
                        'id_viaje'     => $padre->id_viaje,
                        'numero_viaje' => $padre->numero_viaje,
                        'estado'       => $padre->estado,
                    ],
                ], 409);
            }
        }

        $operador = Operador::find($viaje->fk_operador);
        $unidad   = Unidad::find($viaje->fk_unidad);

        return DB::transaction(function () use ($request, $viaje, $operador, $unidad) {
            if ($operador) {
                OperadorHistorialEstado::create([
                    'fk_operador'     => $operador->id_operador,
                    'fk_coordinador'  => Auth::id(),
                    'estado_anterior' => $operador->estado_operador,
                    'estado_nuevo'    => 'EN_VIAJE',
                    'motivo'          => "Inicio del viaje #{$viaje->numero_viaje}",
                    'created_at'      => now(),
                ]);
                DB::table('operador')->where('id_operador', $operador->id_operador)
                    ->update(['estado_operador' => 'EN_VIAJE']);
            }

            if ($unidad) {
                DB::table('reporte_unidad')->insert([
                    'fk_unidad'       => $unidad->id_unidad,
                    'estado_anterior' => $unidad->estado,
                    'estado_nuevo'    => 'EN_VIAJE',
                    'motivo'          => "Inicio del viaje #{$viaje->numero_viaje}",
                    'fecha_reporte'   => now(),
                ]);
                DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                    ->update(['estado' => 'EN_VIAJE']);
            }

            ViajeIncidencia::create([
                'fk_viaje'       => $viaje->id_viaje,
                'fk_operador'    => $viaje->fk_operador,
                'fk_coordinador' => Auth::id(),
                'tipo_evento'    => 'INICIO_VIAJE',
                'detalle'        => "Viaje iniciado. Fecha: {$request->input('fecha_inicio')}",
                'created_at'     => now(),
            ]);

            $viaje->update(['estado' => 'EN_CURSO', 'fecha_salida' => $request->input('fecha_inicio')]);

            return response()->json([
                'ok'  => true,
                'msg' => 'Viaje iniciado correctamente',
                'data' => [
                    'estado_viaje'    => 'EN_CURSO',
                    'estado_operador' => 'EN_VIAJE',
                    'estado_unidad'   => 'EN_VIAJE',
                    'fecha_salida'    => $request->input('fecha_inicio'),
                ],
            ]);
        });
    }

    // ── CANCELAR VIAJE ─────────────────────────────────────────────────────
    // CAMBIO: usa $this->buscarCuota() en lugar de buscar solo por hoy.
    // Esto permite restar la tarifa aunque el periodo haya vencido,
    // siempre que el viaje se hubiera asignado dentro del periodo.
    public function cancelarViaje(Request $request, $id)
    {
        $request->validate([
            'motivos'                   => 'required|string|max:500',
            'nuevo_estado_operador'     => 'nullable|string|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO',
            'motivo_cambio_operador'    => 'nullable|string|max:255',
            'nueva_zona_operador'       => 'nullable|integer|exists:zona,id_zona',
            'nuevo_estado_unidad'       => 'nullable|string|in:DISPONIBLE,NO_DISPONIBLE,EN_VIAJE,MANTENIMIENTO,BAJA',
            'motivo_cambio_unidad'      => 'nullable|string|max:255',
            'nueva_zona_unidad'         => 'nullable|integer|exists:zona,id_zona',
            'accion_viajes_encadenados' => 'nullable|string|in:continuar,liberar',
        ]);

        $viaje = Viaje::find($id);
        if (!$viaje) {
            return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
        }

        if (!in_array($viaje->estado, ['ASIGNADO', 'EN_CURSO', 'PENDIENTE'])) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Solo se pueden cancelar viajes en estado ASIGNADO, EN_CURSO o PENDIENTE.',
            ], 409);
        }

        $esHijoEncadenado = !is_null($viaje->fk_viaje_padre)
            || DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $viaje->id_viaje)->exists();

        $esPadreEnCurso = $viaje->estado === 'EN_CURSO';

        $viajesEncadenadosHijos = DB::table('viaje_encadenamiento as ve')
            ->join('viaje as v', 'v.id_viaje', '=', 've.fk_viaje_hijo')
            ->where('ve.fk_viaje_padre', $id)
            ->whereIn('v.estado', ['PENDIENTE', 'ASIGNADO'])
            ->select('v.id_viaje', 'v.numero_viaje', 'v.estado', 've.orden',
                     'v.fk_operador', 'v.fk_unidad', 'v.pago_operador')
            ->orderBy('ve.orden')
            ->get();

        $accionEncadenados = $request->input('accion_viajes_encadenados', 'liberar');
        $monto             = (float)($viaje->pago_operador ?? 0);
        $operador          = Operador::find($viaje->fk_operador);
        $unidad            = Unidad::find($viaje->fk_unidad);

        return DB::transaction(function () use (
            $request, $viaje, $operador, $unidad, $monto,
            $esHijoEncadenado, $esPadreEnCurso,
            $viajesEncadenadosHijos, $accionEncadenados
        ) {
            $debeRestarTarifa = $operador
                && $monto > 0
                && $viaje->estado !== 'PENDIENTE'
                && $viaje->estado !== 'EN_CURSO';

            if ($debeRestarTarifa) {
                // ── CAMBIO: usar helper en lugar de buscar solo por hoy ──
                $cuota = $this->buscarCuota($operador->id_operador, $viaje);

                if ($cuota) {
                    DB::table('operador_cuota')
                        ->where('id_op_cuota', $cuota->id_op_cuota)
                        ->update(['cuota_realizada' => (float)$cuota->cuota_realizada - $monto]);

                    OperadorMovimiento::create([
                        'fk_operador'    => $operador->id_operador,
                        'fk_viaje'       => $viaje->id_viaje,
                        'fk_coordinador' => Auth::id(),
                        'periodo'        => $cuota->periodo,
                        'tipo'           => 'CANCELACION',
                        'monto'          => -$monto,
                        'descripcion'    => "Cancelación del viaje #{$viaje->numero_viaje}. Motivo: {$request->input('motivos')}",
                        'created_at'     => now(),
                    ]);
                }
            }

            $tieneHijos        = $viajesEncadenadosHijos->isNotEmpty();
            $cancelaSoloPadre  = $esPadreEnCurso && $tieneHijos && $accionEncadenados === 'continuar';
            $operadorBloqueado = $esHijoEncadenado || $cancelaSoloPadre;

            if ($operador && $unidad && !$operadorBloqueado) {
                $nuevoEstadoOp  = $request->input('nuevo_estado_operador', 'DISPONIBLE');
                $motivoCambioOp = $request->input('motivo_cambio_operador')
                    ?? "Cancelación del viaje #{$viaje->numero_viaje}";

                OperadorHistorialEstado::create([
                    'fk_operador'     => $operador->id_operador,
                    'fk_coordinador'  => Auth::id(),
                    'estado_anterior' => $operador->estado_operador,
                    'estado_nuevo'    => $nuevoEstadoOp,
                    'motivo'          => $motivoCambioOp,
                    'created_at'      => now(),
                ]);
                DB::table('operador')->where('id_operador', $operador->id_operador)
                    ->update(['estado_operador' => $nuevoEstadoOp]);

                if ($nuevaZonaOp = $request->input('nueva_zona_operador')) {
                    if ((int)$nuevaZonaOp !== (int)$operador->fk_zona_actual) {
                        OperadorHistorialZona::create([
                            'fk_operador'    => $operador->id_operador,
                            'fk_coordinador' => Auth::id(),
                            'fk_viaje'       => $viaje->id_viaje,
                            'zona_anterior'  => $operador->fk_zona_actual,
                            'zona_nueva'     => $nuevaZonaOp,
                            'motivo'         => $motivoCambioOp,
                            'created_at'     => now(),
                        ]);
                        DB::table('operador')->where('id_operador', $operador->id_operador)
                            ->update(['fk_zona_actual' => $nuevaZonaOp]);
                    }
                }

                $nuevoEstadoUni  = $request->input('nuevo_estado_unidad', 'DISPONIBLE');
                $motivoCambioUni = $request->input('motivo_cambio_unidad')
                    ?? "Cancelación del viaje #{$viaje->numero_viaje}";

                DB::table('reporte_unidad')->insert([
                    'fk_unidad'       => $unidad->id_unidad,
                    'estado_anterior' => $unidad->estado,
                    'estado_nuevo'    => $nuevoEstadoUni,
                    'motivo'          => $motivoCambioUni,
                    'fecha_reporte'   => now(),
                ]);
                DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                    ->update(['estado' => $nuevoEstadoUni]);

                if ($nuevaZonaUni = $request->input('nueva_zona_unidad')) {
                    if ((int)$nuevaZonaUni !== (int)$unidad->fk_zona_actual) {
                        UnidadHistorialZona::create([
                            'fk_unidad'      => $unidad->id_unidad,
                            'fk_coordinador' => Auth::id(),
                            'fk_viaje'       => $viaje->id_viaje,
                            'zona_anterior'  => $unidad->fk_zona_actual,
                            'zona_nueva'     => $nuevaZonaUni,
                            'motivo'         => $motivoCambioUni,
                            'created_at'     => now(),
                        ]);
                        DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                            ->update(['fk_zona_actual' => $nuevaZonaUni]);
                    }
                }

            } elseif ($operador && $unidad && $cancelaSoloPadre) {
                OperadorHistorialEstado::create([
                    'fk_operador'     => $operador->id_operador,
                    'fk_coordinador'  => Auth::id(),
                    'estado_anterior' => $operador->estado_operador,
                    'estado_nuevo'    => 'ASIGNADO',
                    'motivo'          => "Cancelación del viaje padre #{$viaje->numero_viaje}. Operador continúa con viaje encadenado.",
                    'created_at'      => now(),
                ]);
                DB::table('operador')->where('id_operador', $operador->id_operador)
                    ->update(['estado_operador' => 'ASIGNADO']);

                DB::table('reporte_unidad')->insert([
                    'fk_unidad'       => $unidad->id_unidad,
                    'estado_anterior' => $unidad->estado,
                    'estado_nuevo'    => 'ASIGNADA_A_VIAJE',
                    'motivo'          => "Cancelación del viaje padre #{$viaje->numero_viaje}.",
                    'fecha_reporte'   => now(),
                ]);
                DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                    ->update(['estado' => 'ASIGNADA_A_VIAJE']);
            }

            foreach ($viajesEncadenadosHijos as $hijo) {
                if ($accionEncadenados === 'liberar') {
                    $montoHijo = (float)($hijo->pago_operador ?? 0);
                    if ($montoHijo > 0 && $hijo->fk_operador) {
                        // Hijo ASIGNADO — buscar su cuota por fecha_salida del hijo
                        $viajeHijo = Viaje::find($hijo->id_viaje);
                        $cuotaHijo = $viajeHijo
                            ? $this->buscarCuota($hijo->fk_operador, $viajeHijo)
                            : null;

                        if ($cuotaHijo) {
                            DB::table('operador_cuota')
                                ->where('id_op_cuota', $cuotaHijo->id_op_cuota)
                                ->update(['cuota_realizada' => (float)$cuotaHijo->cuota_realizada - $montoHijo]);

                            OperadorMovimiento::create([
                                'fk_operador'    => $hijo->fk_operador,
                                'fk_viaje'       => $hijo->id_viaje,
                                'fk_coordinador' => Auth::id(),
                                'periodo'        => $cuotaHijo->periodo,
                                'tipo'           => 'CANCELACION',
                                'monto'          => -$montoHijo,
                                'descripcion'    => "Cancelación del viaje hijo #{$hijo->numero_viaje} junto al padre #{$viaje->numero_viaje}",
                                'created_at'     => now(),
                            ]);
                        }
                    }

                    DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $hijo->id_viaje)->delete();
                    DB::table('viaje')->where('id_viaje', $hijo->id_viaje)->update([
                        'estado'         => 'CANCELADO',
                        'fk_operador'    => null,
                        'fk_unidad'      => null,
                        'fk_viaje_padre' => null,
                        'fecha_llegada'  => now(),
                    ]);
                    DB::table('viaje_rechazado')->insert([
                        'fk_viaje'      => $hijo->id_viaje,
                        'motivos'       => "Cancelado junto al viaje padre #{$viaje->numero_viaje}. {$request->input('motivos')}",
                        'fecha_rechazo' => now(),
                    ]);
                    ViajeIncidencia::create([
                        'fk_viaje'       => $hijo->id_viaje,
                        'fk_operador'    => $hijo->fk_operador,
                        'fk_coordinador' => Auth::id(),
                        'tipo_evento'    => 'CANCELACION_VIAJE',
                        'detalle'        => "Cancelado junto al viaje padre #{$viaje->numero_viaje}.",
                        'created_at'     => now(),
                    ]);
                } else {
                    DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $hijo->id_viaje)->delete();
                    DB::table('viaje')->where('id_viaje', $hijo->id_viaje)->update(['fk_viaje_padre' => null]);
                    ViajeIncidencia::create([
                        'fk_viaje'       => $hijo->id_viaje,
                        'fk_operador'    => $viaje->fk_operador,
                        'fk_coordinador' => Auth::id(),
                        'tipo_evento'    => 'ENCADENAMIENTO_CONTINUADO',
                        'detalle'        => json_encode([
                            'motivo'       => 'Viaje padre cancelado. Este viaje se convierte en el nuevo padre.',
                            'viaje_padre'  => $viaje->id_viaje,
                            'numero_padre' => $viaje->numero_viaje,
                        ]),
                        'created_at'     => now(),
                    ]);
                }
            }

            if ($esHijoEncadenado) {
                DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $viaje->id_viaje)->delete();
            }

            $viaje->update(['estado' => 'CANCELADO', 'fecha_llegada' => now()]);

            DB::table('viaje_rechazado')->insert([
                'fk_viaje'      => $viaje->id_viaje,
                'motivos'       => $request->input('motivos'),
                'fecha_rechazo' => now(),
            ]);

            ViajeIncidencia::create([
                'fk_viaje'       => $viaje->id_viaje,
                'fk_operador'    => $viaje->fk_operador ?? null,
                'fk_coordinador' => Auth::id(),
                'tipo_evento'    => 'CANCELACION_VIAJE',
                'detalle'        => $request->input('motivos'),
                'created_at'     => now(),
            ]);

            return response()->json([
                'ok'                           => true,
                'msg'                          => 'Viaje cancelado correctamente',
                'es_hijo_encadenado'           => $esHijoEncadenado,
                'viajes_encadenados_afectados' => $viajesEncadenadosHijos->count(),
                'accion_encadenados'           => $accionEncadenados,
                'tarifa_restada'               => $debeRestarTarifa,
            ], 200);
        });
    }

    // ── REASIGNAR ──────────────────────────────────────────────────────────
    // CAMBIO: usa $this->buscarCuota() para encontrar la cuota correcta
    // aunque el periodo haya vencido desde la asignación original.
    public function reasignar(Request $request, $id)
    {
        $request->validate([
            'nuevo_estado_operador' => 'nullable|string|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO',
            'nuevo_estado_unidad'   => 'nullable|string|in:DISPONIBLE,NO_DISPONIBLE,MANTENIMIENTO,BAJA',
            'nueva_zona_operador'   => 'nullable|integer|exists:zona,id_zona',
            'nueva_zona_unidad'     => 'nullable|integer|exists:zona,id_zona',
            'motivo'                => 'nullable|string|max:500',
        ]);

        $viaje = Viaje::find($id);
        if (!$viaje) {
            return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
        }

        if ($viaje->estado !== 'ASIGNADO') {
            return response()->json([
                'ok'  => false,
                'msg' => 'Solo se pueden reasignar viajes en estado ASIGNADO. Los viajes EN_CURSO deben cancelarse.',
            ], 409);
        }

        $operador      = Operador::find($viaje->fk_operador);
        $unidad        = Unidad::find($viaje->fk_unidad);
        $monto         = (float)($viaje->pago_operador ?? 0);
        $motivoInterno = $request->input('motivo') ?? "Reasignación del viaje #{$viaje->numero_viaje}";

        $esHijoEncadenado = !is_null($viaje->fk_viaje_padre)
            || DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $viaje->id_viaje)->exists();

        $estadoOpFinal  = $esHijoEncadenado
            ? ($operador?->estado_operador ?? 'EN_VIAJE')
            : $request->input('nuevo_estado_operador', 'DISPONIBLE');

        $estadoUniFinal = $esHijoEncadenado
            ? ($unidad?->estado ?? 'EN_VIAJE')
            : $request->input('nuevo_estado_unidad', 'DISPONIBLE');

        return DB::transaction(function () use (
            $request, $viaje, $operador, $unidad, $monto,
            $esHijoEncadenado, $motivoInterno, $estadoOpFinal, $estadoUniFinal
        ) {
            if ($operador && $monto > 0) {
                // ── CAMBIO: usar helper en lugar de buscar solo por hoy ──
                $cuota = $this->buscarCuota($operador->id_operador, $viaje);

                if ($cuota) {
                    DB::table('operador_cuota')
                        ->where('id_op_cuota', $cuota->id_op_cuota)
                        ->update(['cuota_realizada' => (float)$cuota->cuota_realizada - $monto]);

                    OperadorMovimiento::create([
                        'fk_operador'    => $operador->id_operador,
                        'fk_viaje'       => $viaje->id_viaje,
                        'fk_coordinador' => Auth::id(),
                        'periodo'        => $cuota->periodo,
                        'tipo'           => 'REASIGNACION',
                        'monto'          => -$monto,
                        'descripcion'    => $motivoInterno,
                        'created_at'     => now(),
                    ]);
                }
            }

            if ($esHijoEncadenado) {
                DB::table('viaje_encadenamiento')->where('fk_viaje_hijo', $viaje->id_viaje)->delete();
            }

            if ($operador && $operador->estado_operador !== $estadoOpFinal) {
                OperadorHistorialEstado::create([
                    'fk_operador'     => $operador->id_operador,
                    'fk_coordinador'  => Auth::id(),
                    'estado_anterior' => $operador->estado_operador,
                    'estado_nuevo'    => $estadoOpFinal,
                    'motivo'          => $motivoInterno,
                    'created_at'      => now(),
                ]);
                DB::table('operador')->where('id_operador', $operador->id_operador)
                    ->update(['estado_operador' => $estadoOpFinal]);
            }

            if ($operador && !$esHijoEncadenado) {
                if ($nuevaZonaOp = $request->input('nueva_zona_operador')) {
                    if ((int)$nuevaZonaOp !== (int)$operador->fk_zona_actual) {
                        OperadorHistorialZona::create([
                            'fk_operador'    => $operador->id_operador,
                            'fk_coordinador' => Auth::id(),
                            'fk_viaje'       => $viaje->id_viaje,
                            'zona_anterior'  => $operador->fk_zona_actual,
                            'zona_nueva'     => $nuevaZonaOp,
                            'motivo'         => $motivoInterno,
                            'created_at'     => now(),
                        ]);
                        DB::table('operador')->where('id_operador', $operador->id_operador)
                            ->update(['fk_zona_actual' => $nuevaZonaOp]);
                    }
                }
            }

            if ($unidad && $unidad->estado !== $estadoUniFinal) {
                DB::table('reporte_unidad')->insert([
                    'fk_unidad'       => $unidad->id_unidad,
                    'estado_anterior' => $unidad->estado,
                    'estado_nuevo'    => $estadoUniFinal,
                    'motivo'          => $motivoInterno,
                    'fecha_reporte'   => now(),
                ]);
                DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                    ->update(['estado' => $estadoUniFinal]);
            }

            if ($unidad && !$esHijoEncadenado) {
                if ($nuevaZonaUni = $request->input('nueva_zona_unidad')) {
                    if ((int)$nuevaZonaUni !== (int)$unidad->fk_zona_actual) {
                        UnidadHistorialZona::create([
                            'fk_unidad'      => $unidad->id_unidad,
                            'fk_coordinador' => Auth::id(),
                            'fk_viaje'       => $viaje->id_viaje,
                            'zona_anterior'  => $unidad->fk_zona_actual,
                            'zona_nueva'     => $nuevaZonaUni,
                            'motivo'         => $motivoInterno,
                            'created_at'     => now(),
                        ]);
                        DB::table('unidad')->where('id_unidad', $unidad->id_unidad)
                            ->update(['fk_zona_actual' => $nuevaZonaUni]);
                    }
                }
            }

            DB::table('historial_reasignaciones')->insert([
                'fk_viaje'       => $viaje->id_viaje,
                'fk_operador'    => $viaje->fk_operador,
                'fk_ruta'        => $viaje->fk_ruta,
                'numero_viaje'   => $viaje->numero_viaje,
                'monto'          => $monto,
                'fk_coordinador' => Auth::id(),
                'motivos'        => $motivoInterno,
                'created_at'     => now(),
            ]);

            ViajeIncidencia::create([
                'fk_viaje'       => $viaje->id_viaje,
                'fk_operador'    => $viaje->fk_operador,
                'fk_coordinador' => Auth::id(),
                'tipo_evento'    => 'REASIGNACION',
                'detalle'        => $motivoInterno,
                'created_at'     => now(),
            ]);

            $viaje->update([
                'estado'         => 'PENDIENTE',
                'fk_operador'    => null,
                'fk_unidad'      => null,
                'fk_viaje_padre' => null,
                'fecha_salida'   => null,
            ]);

            return response()->json([
                'ok'                         => true,
                'msg'                        => 'Viaje reasignado correctamente',
                'es_hijo'                    => $esHijoEncadenado,
                'estado_operador_resultante' => $estadoOpFinal,
                'estado_unidad_resultante'   => $estadoUniFinal,
            ]);
        });
    }

    // ── CAMBIAR TARIFA ─────────────────────────────────────────────────────
    // CAMBIO: usa $this->buscarCuota() para encontrar la cuota correcta.
    // Si no hay cuota, igual registra el movimiento (para no perder el historial)
    // pero no actualiza cuota_realizada (no hay cuota que actualizar).
    public function cambiarTarifa(Request $request, $id)
    {
        $request->validate(['nueva_tarifa' => 'required|numeric|min:0']);

        $id_viaje     = (int) $id;
        $nueva_tarifa = (float) $request->input('nueva_tarifa');

        try {
            return DB::transaction(function () use ($id_viaje, $nueva_tarifa) {

                $viaje = Viaje::find($id_viaje);
                if (!$viaje) {
                    return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
                }

                if (!in_array($viaje->estado, ['ASIGNADO', 'EN_CURSO'])) {
                    return response()->json(['ok' => false, 'msg' => 'No se puede modificar la tarifa en estado: ' . $viaje->estado], 422);
                }

                if (!$viaje->fk_operador) {
                    return response()->json(['ok' => false, 'msg' => 'El viaje no tiene operador asignado'], 422);
                }

                $tarifa_anterior = (float)($viaje->pago_operador ?? 0);
                $diferencia      = $nueva_tarifa - $tarifa_anterior;

                $viaje->update(['pago_operador' => $nueva_tarifa]);

                // ── CAMBIO: usar helper en lugar de buscar solo por hoy ──
                $cuotaActiva = $this->buscarCuota($viaje->fk_operador, $viaje);

                $periodo = $cuotaActiva?->periodo ?? now()->format('Ym');

                if ($diferencia != 0 && $cuotaActiva) {
                    DB::table('operador_cuota')
                        ->where('id_op_cuota', $cuotaActiva->id_op_cuota)
                        ->update(['cuota_realizada' => DB::raw('cuota_realizada + ' . $diferencia)]);
                }

                // El movimiento siempre se registra para no perder el historial
                OperadorMovimiento::create([
                    'fk_operador'    => $viaje->fk_operador,
                    'fk_viaje'       => $id_viaje,
                    'fk_coordinador' => Auth::id(),
                    'periodo'        => $periodo,
                    'tipo'           => 'CAMBIO_TARIFA',
                    'monto'          => $diferencia,
                    'descripcion'    => "Cambio de tarifa en viaje #{$viaje->numero_viaje}. Anterior: $tarifa_anterior, Nueva: $nueva_tarifa",
                    'created_at'     => now(),
                ]);

                ViajeIncidencia::create([
                    'fk_viaje'       => $id_viaje,
                    'fk_operador'    => $viaje->fk_operador,
                    'fk_coordinador' => Auth::id(),
                    'tipo_evento'    => 'CAMBIO_TARIFA',
                    'detalle'        => json_encode([
                        'tarifa_anterior' => $tarifa_anterior,
                        'tarifa_nueva'    => $nueva_tarifa,
                        'diferencia'      => $diferencia,
                    ]),
                    'created_at'     => now(),
                ]);

                return response()->json([
                    'ok'              => true,
                    'msg'             => 'Tarifa actualizada correctamente',
                    'tarifa_anterior' => $tarifa_anterior,
                    'tarifa_nueva'    => $nueva_tarifa,
                    'diferencia'      => $diferencia,
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => 'Error al cambiar la tarifa', 'detail' => $e->getMessage()], 500);
        }
    }

    public function finalizar(Request $request, $id)
    {
        $request->validate([
            'tipo_finalizacion'  => 'required|in:CORRECTO,CON_INCIDENCIA',
            'fecha_llegada_real' => 'required|date',
            'notas'              => 'nullable|string|max:1000',
        ]);

        $viaje = Viaje::with('ruta')->find($id);
        if (!$viaje) {
            return response()->json(['ok' => false, 'msg' => 'Viaje no encontrado'], 404);
        }
        if ($viaje->estado !== 'EN_CURSO') {
            return response()->json(['ok' => false, 'msg' => 'Solo se pueden finalizar viajes en estado EN_CURSO.'], 409);
        }
        if ($request->input('tipo_finalizacion') === 'CON_INCIDENCIA' && empty(trim($request->input('notas', '')))) {
            return response()->json(['ok' => false, 'msg' => 'Las notas son obligatorias cuando hay incidencia.'], 422);
        }

        $operador    = Operador::find($viaje->fk_operador);
        $unidad      = Unidad::find($viaje->fk_unidad);
        $zonaDestino = $viaje->ruta?->fk_zona_destino;

        return DB::transaction(function () use ($request, $viaje, $operador, $unidad, $zonaDestino) {

            $hijosActivos = DB::table('viaje_encadenamiento as ve')
                ->join('viaje as v', 'v.id_viaje', '=', 've.fk_viaje_hijo')
                ->where('ve.fk_viaje_padre', $viaje->id_viaje)
                ->whereIn('v.estado', ['PENDIENTE', 'ASIGNADO'])
                ->orderBy('ve.orden')
                ->select('ve.id', 've.fk_viaje_hijo', 've.orden')
                ->get();

            if ($hijosActivos->isNotEmpty()) {
                $primerHijo = $hijosActivos->first();

                DB::table('viaje')->where('id_viaje', $primerHijo->fk_viaje_hijo)
                    ->update(['fk_viaje_padre' => null]);
                DB::table('viaje_encadenamiento')->where('id', $primerHijo->id)->delete();

                if ($hijosActivos->count() > 1) {
                    foreach ($hijosActivos->skip(1) as $hijo) {
                        DB::table('viaje_encadenamiento')->where('id', $hijo->id)->update([
                            'fk_viaje_padre' => $primerHijo->fk_viaje_hijo,
                            'fk_operador'    => $operador?->id_operador,
                        ]);
                        DB::table('viaje')->where('id_viaje', $hijo->fk_viaje_hijo)
                            ->update(['fk_viaje_padre' => $primerHijo->fk_viaje_hijo]);
                    }
                }

                ViajeIncidencia::create([
                    'fk_viaje'       => $primerHijo->fk_viaje_hijo,
                    'fk_operador'    => $operador?->id_operador,
                    'fk_coordinador' => Auth::id(),
                    'tipo_evento'    => 'ENCADENAMIENTO_LIBERADO',
                    'detalle'        => json_encode([
                        'motivo'       => 'Viaje padre finalizado — este viaje se convierte en nuevo padre',
                        'numero_padre' => $viaje->numero_viaje,
                    ]),
                    'created_at'     => now(),
                ]);
            } else {
                DB::table('viaje_encadenamiento')->where('fk_viaje_padre', $viaje->id_viaje)->delete();
            }

            $nuevoEstadoOp  = $hijosActivos->isNotEmpty() ? 'ASIGNADO'         : 'DISPONIBLE';
            $nuevoEstadoUni = $hijosActivos->isNotEmpty() ? 'ASIGNADA_A_VIAJE' : 'DISPONIBLE';

            if ($operador) {
                OperadorHistorialEstado::create([
                    'fk_operador'     => $operador->id_operador,
                    'fk_coordinador'  => Auth::id(),
                    'estado_anterior' => $operador->estado_operador,
                    'estado_nuevo'    => $nuevoEstadoOp,
                    'motivo'          => "Finalización del viaje #{$viaje->numero_viaje}",
                    'created_at'      => now(),
                ]);

                if ($zonaDestino && (int)$zonaDestino !== (int)$operador->fk_zona_actual) {
                    OperadorHistorialZona::create([
                        'fk_operador'    => $operador->id_operador,
                        'fk_coordinador' => Auth::id(),
                        'fk_viaje'       => $viaje->id_viaje,
                        'zona_anterior'  => $operador->fk_zona_actual,
                        'zona_nueva'     => $zonaDestino,
                        'motivo'         => "Finalización del viaje #{$viaje->numero_viaje}",
                        'created_at'     => now(),
                    ]);
                }

                DB::table('operador')->where('id_operador', $operador->id_operador)->update([
                    'estado_operador' => $nuevoEstadoOp,
                    'fk_zona_actual'  => $zonaDestino ?? $operador->fk_zona_actual,
                ]);
            }

            if ($unidad) {
                DB::table('reporte_unidad')->insert([
                    'fk_unidad'       => $unidad->id_unidad,
                    'estado_anterior' => $unidad->estado,
                    'estado_nuevo'    => $nuevoEstadoUni,
                    'motivo'          => "Finalización del viaje #{$viaje->numero_viaje}",
                    'fecha_reporte'   => now(),
                ]);

                if ($zonaDestino && (int)$zonaDestino !== (int)$unidad->fk_zona_actual) {
                    UnidadHistorialZona::create([
                        'fk_unidad'      => $unidad->id_unidad,
                        'fk_coordinador' => Auth::id(),
                        'fk_viaje'       => $viaje->id_viaje,
                        'zona_anterior'  => $unidad->fk_zona_actual,
                        'zona_nueva'     => $zonaDestino,
                        'motivo'         => "Finalización del viaje #{$viaje->numero_viaje}",
                        'created_at'     => now(),
                    ]);
                }

                DB::table('unidad')->where('id_unidad', $unidad->id_unidad)->update([
                    'estado'         => $nuevoEstadoUni,
                    'fk_zona_actual' => $zonaDestino ?? $unidad->fk_zona_actual,
                ]);
            }

            ViajeFinalizacion::create([
                'fk_viaje'           => $viaje->id_viaje,
                'fk_coordinador'     => Auth::id(),
                'tipo_finalizacion'  => $request->input('tipo_finalizacion'),
                'notas'              => $request->input('notas') ?? null,
                'fecha_llegada_real' => $request->input('fecha_llegada_real'),
                'fecha_finalizacion' => now(),
                'created_at'         => now(),
            ]);

            ViajeIncidencia::create([
                'fk_viaje'       => $viaje->id_viaje,
                'fk_operador'    => $viaje->fk_operador,
                'fk_coordinador' => Auth::id(),
                'tipo_evento'    => 'FINALIZACION_VIAJE',
                'detalle'        => $request->input('tipo_finalizacion') === 'CON_INCIDENCIA'
                                    ? $request->input('notas')
                                    : 'Viaje finalizado correctamente.',
                'created_at'     => now(),
            ]);

            $viaje->update([
                'estado'        => 'TERMINADO',
                'fecha_llegada' => $request->input('fecha_llegada_real'),
            ]);

            return response()->json([
                'ok'               => true,
                'msg'              => 'Viaje finalizado correctamente',
                'tiene_encadenado' => $hijosActivos->isNotEmpty(),
            ]);
        });
    }

    public function getFinalizacion($id)
    {
        $finalizacion = ViajeFinalizacion::where('fk_viaje', $id)->first();
        if (!$finalizacion) {
            return response()->json(['ok' => false, 'msg' => 'Sin datos de finalización'], 404);
        }

        $coordinador = DB::table('usuarios')
            ->where('idUsuario', $finalizacion->fk_coordinador)
            ->select(DB::raw("CONCAT(nombre, ' ', apellidos) as nombre_coordinador"))
            ->first();

        return response()->json([
            'ok'           => true,
            'finalizacion' => array_merge(
                $finalizacion->toArray(),
                ['nombre_coordinador' => $coordinador?->nombre_coordinador ?? '—']
            ),
        ]);
    }

    public function rechazar(Request $request, $id_viaje, $id_operador)
    {
        $request->validate(['motivo' => 'required|string']);
        $motivo = $request->input('motivo');

        DB::table('rechazos_operador')->insert([
            'fk_viaje'    => $id_viaje,
            'fk_operador' => $id_operador,
            'motivo'      => $motivo,
            'created_at'  => now(),
        ]);

        ViajeIncidencia::create([
            'fk_viaje'       => $id_viaje,
            'fk_operador'    => $id_operador,
            'fk_coordinador' => Auth::id(),
            'tipo_evento'    => 'RECHAZO_OPERADOR',
            'detalle'        => $motivo,
            'created_at'     => now(),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Operador rechazado']);
    }

    public function historialViaje($id_viaje)
    {
        $incidencias = DB::table('viaje_incidencia as vi')
            ->leftJoin('operador as op', 'op.id_operador', '=', 'vi.fk_operador')
            ->leftJoin('usuarios as u',  'u.idUsuario',    '=', 'vi.fk_coordinador')
            ->where('vi.fk_viaje', $id_viaje)
            ->orderBy('vi.created_at', 'asc')
            ->select([
                'vi.id_incidencia', 'vi.tipo_evento', 'vi.detalle', 'vi.created_at',
                'vi.adv_unidad_no_disponible', 'vi.adv_licencia_vencida',
                'vi.adv_licencia_incorrecta',  'vi.adv_operador_fuera_zona',
                'vi.adv_unidad_fuera_zona',    'vi.adv_cuota_agotada',
                'vi.adv_certificaciones_faltantes',
                DB::raw("CONCAT(op.nombres, ' ', op.apellidos) as nombre_operador"),
                'op.numero_empleado',
                DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
            ])->get();

        return response()->json(['ok' => true, 'historial' => $incidencias]);
    }

    public function historialEstadosOperador($id_operador)
    {
        $historial = DB::table('operador_historial_estado as ohe')
            ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'ohe.fk_coordinador')
            ->where('ohe.fk_operador', $id_operador)
            ->orderBy('ohe.created_at', 'desc')
            ->select([
                'ohe.id', 'ohe.estado_anterior', 'ohe.estado_nuevo', 'ohe.motivo', 'ohe.created_at',
                DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
            ])->get();

        return response()->json(['ok' => true, 'historial' => $historial]);
    }

    public function historialZonasOperador($id_operador)
    {
        $historial = DB::table('operador_historial_zona as ohz')
            ->leftJoin('zona as za', 'za.id_zona', '=', 'ohz.zona_anterior')
            ->leftJoin('zona as zn', 'zn.id_zona', '=', 'ohz.zona_nueva')
            ->leftJoin('viaje as v',  'v.id_viaje',  '=', 'ohz.fk_viaje')
            ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'ohz.fk_coordinador')
            ->where('ohz.fk_operador', $id_operador)
            ->orderBy('ohz.created_at', 'desc')
            ->select([
                'ohz.id', 'ohz.motivo', 'ohz.created_at',
                'za.nombre_zona as zona_anterior', 'zn.nombre_zona as zona_nueva',
                'v.numero_viaje',
                DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
            ])->get();

        return response()->json(['ok' => true, 'historial' => $historial]);
    }

    public function historialZonasUnidad($id_unidad)
    {
        $historial = DB::table('unidad_historial_zona as uhz')
            ->leftJoin('zona as za', 'za.id_zona', '=', 'uhz.zona_anterior')
            ->leftJoin('zona as zn', 'zn.id_zona', '=', 'uhz.zona_nueva')
            ->leftJoin('viaje as v',  'v.id_viaje',  '=', 'uhz.fk_viaje')
            ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'uhz.fk_coordinador')
            ->where('uhz.fk_unidad', $id_unidad)
            ->orderBy('uhz.created_at', 'desc')
            ->select([
                'uhz.id', 'uhz.motivo', 'uhz.created_at',
                'za.nombre_zona as zona_anterior', 'zn.nombre_zona as zona_nueva',
                'v.numero_viaje',
                DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
            ])->get();

        return response()->json(['ok' => true, 'historial' => $historial]);
    }

    public function movimientosOperador(Request $request, $id_operador)
    {
        $fechaFin    = $request->filled('fecha_fin')
            ? $request->fecha_fin
            : now()->toDateString();

        $fechaInicio = $request->filled('fecha_inicio')
            ? $request->fecha_inicio
            : now()->subDays(6)->toDateString();

        $query = DB::table('operador_movimiento as om')
            ->leftJoin('viaje as v',    'v.id_viaje',  '=', 'om.fk_viaje')
            ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'om.fk_coordinador')
            ->where('om.fk_operador', $id_operador)
            ->whereBetween(DB::raw('DATE(om.created_at)'), [$fechaInicio, $fechaFin])
            ->orderBy('om.created_at', 'desc')
            ->select([
                'om.id_movimiento', 'om.tipo', 'om.monto', 'om.descripcion',
                'om.periodo', 'om.created_at',
                'v.numero_viaje', 'v.estado as estado_viaje',
                DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
            ]);

        $movimientos   = $query->get();
        $totalIngresos = $movimientos->where('monto', '>', 0)->sum('monto');
        $totalEgresos  = $movimientos->where('monto', '<', 0)->sum('monto');

        return response()->json([
            'ok'          => true,
            'movimientos' => $movimientos,
            'totales'     => [
                'ingresos' => $totalIngresos,
                'egresos'  => abs($totalEgresos),
                'balance'  => $totalIngresos + $totalEgresos,
            ],
        ]);
    }

    public function obtenerCadena($id_viaje)
    {
        $cadena = DB::table('viaje_encadenamiento as ve')
            ->join('viaje as vp', 'vp.id_viaje', '=', 've.fk_viaje_padre')
            ->join('viaje as vh', 'vh.id_viaje', '=', 've.fk_viaje_hijo')
            ->join('ruta as rp',  'rp.id_ruta',  '=', 'vp.fk_ruta')
            ->join('ruta as rh',  'rh.id_ruta',  '=', 'vh.fk_ruta')
            ->where(function ($q) use ($id_viaje) {
                $q->where('ve.fk_viaje_padre', $id_viaje)
                  ->orWhere('ve.fk_viaje_hijo', $id_viaje);
            })
            ->select([
                've.id', 've.orden', 've.fk_operador',
                'vp.id_viaje as id_padre',    'vp.numero_viaje as numero_padre',
                'vp.estado   as estado_padre', 'rp.nombre_ruta  as ruta_padre',
                'vh.id_viaje as id_hijo',     'vh.numero_viaje as numero_hijo',
                'vh.estado   as estado_hijo',  'rh.nombre_ruta  as ruta_hijo',
            ])
            ->orderBy('ve.orden')
            ->get();

        return response()->json(['ok' => true, 'cadena' => $cadena]);
    }
}