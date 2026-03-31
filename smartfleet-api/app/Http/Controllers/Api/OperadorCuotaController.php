<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operador;
use App\Models\OperadorCuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OperadorCuotaController extends Controller
{
    public function index(Request $request)
    {
        $query = OperadorCuota::with('operador');

        if ($request->filled('fk_operador')) {
            $query->where('fk_operador', $request->fk_operador);
        }
        if ($request->filled('periodo')) {
            $query->where('periodo', $request->periodo);
        }

        $cuotas = $query->orderByDesc('fecha_inicio')->orderByDesc('id_op_cuota')->get();

        return response()->json(['ok' => true, 'cuotas' => $cuotas]);
    }

    public function porOperador($idOperador)
    {
        $operador = Operador::find($idOperador);
        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

        $hoy    = now()->toDateString();
        $cuotas = OperadorCuota::where('fk_operador', $idOperador)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id_op_cuota')
            ->get();

        // Cuota activa = la que cubre hoy
        $actual = $cuotas->first(function ($c) use ($hoy) {
            return $c->fecha_inicio <= $hoy && $c->fecha_fin >= $hoy;
        }) ?? $cuotas->first();

        return response()->json([
            'ok'      => true,
            'operador' => [
                'id_operador'    => $operador->id_operador,
                'numero_empleado'=> $operador->numero_empleado,
                'nombres'        => $operador->nombres,
                'apellidos'      => $operador->apellidos,
                'nombre_completo'=> trim(($operador->nombres ?? '') . ' ' . ($operador->apellidos ?? '')),
            ],
            'resumen' => $actual ? [
                'periodo'         => $actual->periodo,
                'fecha_inicio'    => $actual->fecha_inicio,
                'fecha_fin'       => $actual->fecha_fin,
                'cuota_objetivo'  => $actual->cuota_objetivo,
                'cuota_realizada' => $actual->cuota_realizada,
                'cuota_restante'  => $actual->cuota_restante,
                'estado_cuota'    => $actual->estado_cuota,
            ] : null,
            'cuotas'  => $cuotas,
        ]);
    }

    public function show($id)
    {
        $cuota = OperadorCuota::with('operador')->find($id);
        if (!$cuota) {
            return response()->json(['ok' => false, 'msg' => 'Cuota no encontrada'], 404);
        }
        return response()->json(['ok' => true, 'cuota' => $cuota]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fk_operador'    => 'required|integer|exists:operador,id_operador',
            'fecha_inicio'   => 'required|date',
            'fecha_fin'      => 'required|date|after_or_equal:fecha_inicio',
            'cuota_objetivo' => 'required|integer|min:0|max:65535',
            'cuota_realizada'=> 'nullable|integer|min:0|max:65535',
        ], [
            'fk_operador.required'   => 'El operador es obligatorio.',
            'fk_operador.exists'     => 'El operador no existe.',
            'fecha_inicio.required'  => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required'     => 'La fecha de fin es obligatoria.',
            'fecha_fin.after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha inicio.',
            'cuota_objetivo.required'=> 'La cuota objetivo es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        // ✅ Validar máximo 7 días
        $inicio = Carbon::parse($request->fecha_inicio);
        $fin    = Carbon::parse($request->fecha_fin);
        $dias   = $inicio->diffInDays($fin);

        if ($dias > 6) {
            return response()->json([
                'ok'  => false,
                'msg' => 'El rango no puede exceder 7 días. Actualmente son ' . ($dias + 1) . ' días.',
            ], 422);
        }

        // ✅ Validar que no se solapen con otra cuota del mismo operador
        $solapada = OperadorCuota::where('fk_operador', $request->fk_operador)
            ->where(function ($q) use ($request) {
                $q->whereBetween('fecha_inicio', [$request->fecha_inicio, $request->fecha_fin])
                  ->orWhereBetween('fecha_fin',   [$request->fecha_inicio, $request->fecha_fin])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('fecha_inicio', '<=', $request->fecha_inicio)
                         ->where('fecha_fin',    '>=', $request->fecha_fin);
                  });
            })->first();

        if ($solapada) {
            return response()->json([
                'ok'  => false,
                'msg' => "El rango se solapa con una cuota existente ({$solapada->fecha_inicio} al {$solapada->fecha_fin}).",
            ], 409);
        }

        // ✅ Generar periodo automáticamente del fecha_inicio
        $periodo = $inicio->format('Ym');

        $cuotaObjetivo  = (int) $request->cuota_objetivo;
        $cuotaRealizada = (int) ($request->cuota_realizada ?? 0);

        if ($cuotaRealizada > $cuotaObjetivo) {
            return response()->json([
                'ok' => false, 'msg' => 'La cuota realizada no puede ser mayor que la cuota objetivo.'
            ], 422);
        }

        $cuota = OperadorCuota::create([
            'fk_operador'    => $request->fk_operador,
            'periodo'        => $periodo,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'cuota_objetivo' => $cuotaObjetivo,
            'cuota_realizada'=> $cuotaRealizada,
        ]);

        return response()->json(['ok' => true, 'msg' => 'Cuota creada correctamente.', 'cuota' => $cuota], 201);
    }

    public function update(Request $request, $id)
    {
        $cuota = OperadorCuota::find($id);
        if (!$cuota) {
            return response()->json(['ok' => false, 'msg' => 'Cuota no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio'   => 'sometimes|date',
            'fecha_fin'      => 'sometimes|date|after_or_equal:fecha_inicio',
            'cuota_objetivo' => 'sometimes|integer|min:0|max:65535',
            'cuota_realizada'=> 'sometimes|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $fechaInicio = $request->has('fecha_inicio') ? $request->fecha_inicio : $cuota->fecha_inicio;
        $fechaFin    = $request->has('fecha_fin')    ? $request->fecha_fin    : $cuota->fecha_fin;

        // ✅ Validar máximo 7 días
        $inicio = Carbon::parse($fechaInicio);
        $fin    = Carbon::parse($fechaFin);
        $dias   = $inicio->diffInDays($fin);

        if ($dias > 6) {
            return response()->json([
                'ok'  => false,
                'msg' => 'El rango no puede exceder 7 días.',
            ], 422);
        }

        // ✅ Validar solapamiento excluyendo la cuota actual
        $solapada = OperadorCuota::where('fk_operador', $cuota->fk_operador)
            ->where('id_op_cuota', '!=', $id)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                  ->orWhereBetween('fecha_fin',   [$fechaInicio, $fechaFin])
                  ->orWhere(function ($q2) use ($fechaInicio, $fechaFin) {
                      $q2->where('fecha_inicio', '<=', $fechaInicio)
                         ->where('fecha_fin',    '>=', $fechaFin);
                  });
            })->first();

        if ($solapada) {
            return response()->json([
                'ok'  => false,
                'msg' => "El rango se solapa con una cuota existente ({$solapada->fecha_inicio} al {$solapada->fecha_fin}).",
            ], 409);
        }

        $periodo        = $inicio->format('Ym');
        $cuotaObjetivo  = $request->has('cuota_objetivo')  ? (int)$request->cuota_objetivo  : (int)$cuota->cuota_objetivo;
        $cuotaRealizada = $request->has('cuota_realizada') ? (int)$request->cuota_realizada : (int)$cuota->cuota_realizada;

        if ($cuotaRealizada > $cuotaObjetivo) {
            return response()->json(['ok' => false, 'msg' => 'La cuota realizada no puede ser mayor que la cuota objetivo.'], 422);
        }

        $cuota->update([
            'periodo'        => $periodo,
            'fecha_inicio'   => $fechaInicio,
            'fecha_fin'      => $fechaFin,
            'cuota_objetivo' => $cuotaObjetivo,
            'cuota_realizada'=> $cuotaRealizada,
        ]);

        return response()->json(['ok' => true, 'msg' => 'Cuota actualizada correctamente.', 'cuota' => $cuota->fresh()]);
    }

    public function destroy($id)
    {
        $cuota = OperadorCuota::find($id);
        if (!$cuota) {
            return response()->json(['ok' => false, 'msg' => 'Cuota no encontrada'], 404);
        }
        $cuota->delete();
        return response()->json(['ok' => true, 'msg' => 'Cuota eliminada correctamente.']);
    }
}