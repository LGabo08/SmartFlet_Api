<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    public function resumen()
    {
        $hoy    = now()->toDateString();
        $user   = auth('api')->user();
        $userId = $user->getKey();
        $isAdmin = optional($user->role)->nombre === 'ADMIN';

        // ── KPIs Operadores ───────────────────────────────────────────────
        $qOperadores = DB::table('operador');
        if (!$isAdmin) $qOperadores->where('fk_usuario', $userId);

        $operadores = $qOperadores->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado_operador = 'DISPONIBLE'    THEN 1 ELSE 0 END) as disponibles,
            SUM(CASE WHEN estado_operador = 'EN_VIAJE'      THEN 1 ELSE 0 END) as en_viaje,
            SUM(CASE WHEN estado_operador = 'ASIGNADO'      THEN 1 ELSE 0 END) as asignados,
            SUM(CASE WHEN estado_operador = 'NO_DISPONIBLE' THEN 1 ELSE 0 END) as no_disponibles,
            SUM(CASE WHEN estado_operador = 'INACTIVO'      THEN 1 ELSE 0 END) as inactivos
        ")->first();

        // ── KPIs Viajes ───────────────────────────────────────────────────
        $qViajes = DB::table('viaje');
        if (!$isAdmin) $qViajes->where('fk_usuario', $userId);

        $viajes = $qViajes->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'ASIGNADO'  THEN 1 ELSE 0 END) as asignados,
            SUM(CASE WHEN estado = 'EN_CURSO'  THEN 1 ELSE 0 END) as en_curso,
            SUM(CASE WHEN estado = 'TERMINADO' THEN 1 ELSE 0 END) as terminados,
            SUM(CASE WHEN estado = 'CANCELADO' THEN 1 ELSE 0 END) as cancelados
        ")->first();

        // ── Equilibrio cuotas semana actual ───────────────────────────────
        $qCuotas = DB::table('operador_cuota as oc');
        if (!$isAdmin) {
            $qCuotas->join('operador as op_f', 'op_f.id_operador', '=', 'oc.fk_operador')
                    ->where('op_f.fk_usuario', $userId);
        }

        $cuotasSemana = $qCuotas
            ->where('oc.fecha_inicio', '<=', $hoy)
            ->where('oc.fecha_fin',    '>=', $hoy)
            ->selectRaw('SUM(oc.cuota_objetivo) as objetivo, SUM(oc.cuota_realizada) as realizada')
            ->first();

        $objetivo       = (float)($cuotasSemana->objetivo  ?? 0);
        $realizada      = (float)($cuotasSemana->realizada ?? 0);
        $equilibrioPct  = $objetivo > 0 ? round(($realizada / $objetivo) * 100, 1) : 0;

        // ── Viajes en curso ───────────────────────────────────────────────
        $qEnCurso = DB::table('viaje as v')
            ->join('ruta as r',      'r.id_ruta',      '=', 'v.fk_ruta')
            ->leftJoin('operador as op', 'op.id_operador', '=', 'v.fk_operador')
            ->leftJoin('unidad as u',   'u.id_unidad',    '=', 'v.fk_unidad')
            ->leftJoin('zona as zo',    'zo.id_zona',     '=', 'r.fk_zona_origen')
            ->leftJoin('zona as zd',    'zd.id_zona',     '=', 'r.fk_zona_destino')
            ->where('v.estado', 'EN_CURSO');

        if (!$isAdmin) $qEnCurso->where('v.fk_usuario', $userId);

        $viajesEnCurso = $qEnCurso->select([
                'v.id_viaje',
                'v.numero_viaje',
                'v.fecha_salida',
                'r.nombre_ruta',
                'zo.nombre_zona as zona_origen',
                'zd.nombre_zona as zona_destino',
                DB::raw("CONCAT(op.nombres, ' ', op.apellidos) as nombre_operador"),
                'u.numero_economico',
            ])
            ->orderBy('v.fecha_salida', 'asc')
            ->limit(10)
            ->get();

        // ── Rezagados por cuota semana actual ─────────────────────────────
        $qRezagados = DB::table('operador_cuota as oc')
            ->join('operador as op', 'op.id_operador', '=', 'oc.fk_operador')
            ->where('oc.fecha_inicio', '<=', $hoy)
            ->where('oc.fecha_fin',    '>=', $hoy)
            ->where('oc.cuota_objetivo', '>', 0);

        if (!$isAdmin) $qRezagados->where('op.fk_usuario', $userId);

        $rezagados = $qRezagados->selectRaw("
                op.id_operador,
                CONCAT(op.nombres, ' ', op.apellidos) as nombre_operador,
                op.numero_empleado,
                op.estado_operador,
                oc.cuota_objetivo,
                oc.cuota_realizada,
                ROUND((oc.cuota_realizada / oc.cuota_objetivo) * 100, 1) as pct_cuota
            ")
            ->orderByRaw('pct_cuota ASC')
            ->limit(8)
            ->get();

        // ── Alertas ───────────────────────────────────────────────────────
        $alertas = [];

        // Operadores con cuota < 30%
        $qCuotaBaja = DB::table('operador_cuota as oc')
            ->join('operador as op', 'op.id_operador', '=', 'oc.fk_operador')
            ->where('oc.fecha_inicio', '<=', $hoy)
            ->where('oc.fecha_fin',    '>=', $hoy)
            ->where('oc.cuota_objetivo', '>', 0)
            ->whereRaw('(oc.cuota_realizada / oc.cuota_objetivo) < 0.30');

        if (!$isAdmin) $qCuotaBaja->where('op.fk_usuario', $userId);

        $opsCuotaBaja = $qCuotaBaja->selectRaw("
                op.id_operador,
                CONCAT(op.nombres, ' ', op.apellidos) as nombre_operador,
                ROUND((oc.cuota_realizada / oc.cuota_objetivo) * 100, 1) as pct_cuota
            ")->get();

        if ($opsCuotaBaja->count() > 0) {
            $nombres = $opsCuotaBaja->map(fn($o) => "{$o->nombre_operador} ({$o->pct_cuota}%)")->join(', ');
            $alertas[] = [
                'tipo'    => 'cuota_baja',
                'nivel'   => 'danger',
                'mensaje' => "{$opsCuotaBaja->count()} operador(es) con cuota menor al 30%: {$nombres}",
                'ids'     => $opsCuotaBaja->pluck('id_operador'),
            ];
        }

        // Viajes pendientes sin asignar
        $qPendientes = DB::table('viaje')->where('estado', 'PENDIENTE');
        if (!$isAdmin) $qPendientes->where('fk_usuario', $userId);
        $viajesPendientes = $qPendientes->count();

        if ($viajesPendientes > 0) {
            $alertas[] = [
                'tipo'    => 'viajes_pendientes',
                'nivel'   => 'warning',
                'mensaje' => "{$viajesPendientes} viaje(s) pendiente(s) sin asignar.",
                'ids'     => [],
            ];
        }

        // Viajes asignados sin iniciar hace más de 2 horas
        $qSinIniciar = DB::table('viaje as v')
            ->join('viaje_incidencia as vi', function ($join) {
                $join->on('vi.fk_viaje', '=', 'v.id_viaje')
                     ->where('vi.tipo_evento', 'ASIGNACION_OK')
                     ->orWhere('vi.tipo_evento', 'ASIGNACION_CON_ADVERTENCIAS');
            })
            ->where('v.estado', 'ASIGNADO')
            ->whereRaw('vi.created_at <= NOW() - INTERVAL 2 HOUR');

        if (!$isAdmin) $qSinIniciar->where('v.fk_usuario', $userId);

        $viajesAsignadosSinIniciar = $qSinIniciar
            ->select('v.id_viaje', 'v.numero_viaje', 'vi.created_at as fecha_asignacion')
            ->distinct()
            ->get();

        if ($viajesAsignadosSinIniciar->count() > 0) {
            $alertas[] = [
                'tipo'    => 'asignados_sin_iniciar',
                'nivel'   => 'warning',
                'mensaje' => "{$viajesAsignadosSinIniciar->count()} viaje(s) asignado(s) sin iniciar hace más de 2 horas.",
                'ids'     => $viajesAsignadosSinIniciar->pluck('id_viaje'),
            ];
        }

        return response()->json([
            'ok' => true,
            'kpis' => [
                'operadores' => [
                    'total'          => (int)$operadores->total,
                    'disponibles'    => (int)$operadores->disponibles,
                    'en_viaje'       => (int)$operadores->en_viaje,
                    'asignados'      => (int)$operadores->asignados,
                    'no_disponibles' => (int)$operadores->no_disponibles,
                    'inactivos'      => (int)$operadores->inactivos,
                ],
                'viajes' => [
                    'total'      => (int)$viajes->total,
                    'pendientes' => (int)$viajes->pendientes,
                    'asignados'  => (int)$viajes->asignados,
                    'en_curso'   => (int)$viajes->en_curso,
                    'terminados' => (int)$viajes->terminados,
                    'cancelados' => (int)$viajes->cancelados,
                ],
                'cuotas' => [
                    'objetivo'       => $objetivo,
                    'realizada'      => $realizada,
                    'equilibrio_pct' => $equilibrioPct,
                ],
            ],
            'viajes_en_curso' => $viajesEnCurso,
            'rezagados'       => $rezagados,
            'alertas'         => $alertas,
        ]);
    }
}