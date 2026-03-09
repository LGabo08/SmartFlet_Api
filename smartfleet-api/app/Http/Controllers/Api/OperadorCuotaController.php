<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operador;
use App\Models\OperadorCuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OperadorCuotaController extends Controller
{
    /**
     * Lista todas las cuotas.
     */
    public function index(Request $request)
    {
        $query = OperadorCuota::with('operador');

        if ($request->filled('fk_operador')) {
            $query->where('fk_operador', $request->fk_operador);
        }

        if ($request->filled('periodo')) {
            $query->where('periodo', $request->periodo);
        }

        $cuotas = $query
            ->orderByDesc('periodo')
            ->orderByDesc('id_op_cuota')
            ->get();

        return response()->json([
            'ok' => true,
            'cuotas' => $cuotas
        ]);
    }

    /**
     * Lista cuotas por operador.
     */
    public function porOperador($idOperador)
    {
        $operador = Operador::find($idOperador);

        if (!$operador) {
            return response()->json([
                'ok' => false,
                'msg' => 'Operador no encontrado'
            ], 404);
        }

        $cuotas = OperadorCuota::where('fk_operador', $idOperador)
            ->orderByDesc('periodo')
            ->orderByDesc('id_op_cuota')
            ->get();

        $actual = $cuotas->first();

        return response()->json([
            'ok' => true,
            'operador' => [
                'id_operador' => $operador->id_operador,
                'numero_empleado' => $operador->numero_empleado,
                'nombres' => $operador->nombres,
                'apellidos' => $operador->apellidos,
                'nombre_completo' => trim(($operador->nombres ?? '') . ' ' . ($operador->apellidos ?? '')),
            ],
            'resumen' => $actual ? [
                'periodo' => $actual->periodo,
                'cuota_objetivo' => $actual->cuota_objetivo,
                'cuota_realizada' => $actual->cuota_realizada,
                'cuota_restante' => $actual->cuota_restante,
                'estado_cuota' => $actual->estado_cuota,
            ] : null,
            'cuotas' => $cuotas,
        ]);
    }

    /**
     * Ver una cuota por ID.
     */
    public function show($id)
    {
        $cuota = OperadorCuota::with('operador')->find($id);

        if (!$cuota) {
            return response()->json([
                'ok' => false,
                'msg' => 'Cuota no encontrada'
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'cuota' => $cuota
        ]);
    }

    /**
     * Crear cuota.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fk_operador' => 'required|integer|exists:operador,id_operador',
            'periodo' => ['required', 'digits:6'],
            'cuota_objetivo' => 'required|integer|min:0|max:65535',
            'cuota_realizada' => 'nullable|integer|min:0|max:65535',
        ], [
            'fk_operador.required' => 'El operador es obligatorio.',
            'fk_operador.exists' => 'El operador no existe.',
            'periodo.required' => 'El periodo es obligatorio.',
            'periodo.digits' => 'El periodo debe tener formato YYYYMM.',
            'cuota_objetivo.required' => 'La cuota objetivo es obligatoria.',
            'cuota_realizada.integer' => 'La cuota realizada debe ser numérica.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $existe = OperadorCuota::where('fk_operador', $request->fk_operador)
            ->where('periodo', $request->periodo)
            ->first();

        if ($existe) {
            return response()->json([
                'ok' => false,
                'msg' => 'Ya existe una cuota registrada para este operador en ese periodo.'
            ], 409);
        }

        $cuotaObjetivo = (int) $request->cuota_objetivo;
        $cuotaRealizada = (int) ($request->cuota_realizada ?? 0);

        if ($cuotaRealizada > $cuotaObjetivo) {
            return response()->json([
                'ok' => false,
                'msg' => 'La cuota realizada no puede ser mayor que la cuota objetivo.'
            ], 422);
        }

        $cuota = OperadorCuota::create([
            'fk_operador' => $request->fk_operador,
            'periodo' => $request->periodo,
            'cuota_objetivo' => $cuotaObjetivo,
            'cuota_realizada' => $cuotaRealizada,
        ]);

        return response()->json([
            'ok' => true,
            'msg' => 'Cuota creada correctamente.',
            'cuota' => $cuota
        ], 201);
    }

    /**
     * Actualizar cuota.
     */
    public function update(Request $request, $id)
    {
        $cuota = OperadorCuota::find($id);

        if (!$cuota) {
            return response()->json([
                'ok' => false,
                'msg' => 'Cuota no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'periodo' => ['sometimes', 'digits:6'],
            'cuota_objetivo' => 'sometimes|integer|min:0|max:65535',
            'cuota_realizada' => 'sometimes|integer|min:0|max:65535',
        ], [
            'periodo.digits' => 'El periodo debe tener formato YYYYMM.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $nuevoPeriodo = $request->has('periodo') ? $request->periodo : $cuota->periodo;

        $duplicada = OperadorCuota::where('fk_operador', $cuota->fk_operador)
            ->where('periodo', $nuevoPeriodo)
            ->where('id_op_cuota', '!=', $cuota->id_op_cuota)
            ->exists();

        if ($duplicada) {
            return response()->json([
                'ok' => false,
                'msg' => 'Ya existe otra cuota para este operador en ese periodo.'
            ], 409);
        }

        $cuotaObjetivo = $request->has('cuota_objetivo')
            ? (int) $request->cuota_objetivo
            : (int) $cuota->cuota_objetivo;

        $cuotaRealizada = $request->has('cuota_realizada')
            ? (int) $request->cuota_realizada
            : (int) $cuota->cuota_realizada;

        if ($cuotaRealizada > $cuotaObjetivo) {
            return response()->json([
                'ok' => false,
                'msg' => 'La cuota realizada no puede ser mayor que la cuota objetivo.'
            ], 422);
        }

        $cuota->update([
            'periodo' => $nuevoPeriodo,
            'cuota_objetivo' => $cuotaObjetivo,
            'cuota_realizada' => $cuotaRealizada,
        ]);

        return response()->json([
            'ok' => true,
            'msg' => 'Cuota actualizada correctamente.',
            'cuota' => $cuota->fresh()
        ]);
    }

    /**
     * Eliminar cuota.
     */
    public function destroy($id)
    {
        $cuota = OperadorCuota::find($id);

        if (!$cuota) {
            return response()->json([
                'ok' => false,
                'msg' => 'Cuota no encontrada'
            ], 404);
        }

        $cuota->delete();

        return response()->json([
            'ok' => true,
            'msg' => 'Cuota eliminada correctamente.'
        ]);
    }
}