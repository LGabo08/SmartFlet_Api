<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unidad;
use Illuminate\Http\Request;
use App\Models\ReporteUnidad;
use Illuminate\Support\Facades\DB;
use App\Models\UnidadHistorialOperador;
class UnidadController extends Controller
{
 public function index()
{
    $unidades = DB::table('unidad as u')
        ->leftJoin('zona as z', 'z.id_zona', '=', 'u.fk_zona_actual')
        ->leftJoin('licencia as l', 'l.id_licencia', '=', 'u.fk_licencia_requerida')
        ->select([
            'u.id_unidad',
            'u.numero_economico',
            'u.estado',
            'u.fk_zona_actual',
            'z.nombre_zona',
            'u.fk_licencia_requerida',
            'l.descripcion_licencia',
        ])
        ->get();

    return response()->json([
        'ok'       => true,
        'unidades' => $unidades
    ]);
}

    public function show($id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // ✅ requerido por tu model (incrementing = false)
            'id_unidad' => 'required|integer|unique:unidad,id_unidad',

            'numero_economico' => 'required|string|max:255',
            'fk_zona_actual' => 'required|exists:zona,id_zona',
            'estado' => 'required|string|max:50',
            'fk_licencia_requerida' => 'nullable|exists:licencia,id_licencia',
        ]);

        $unidad = Unidad::create($validated);

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        $validated = $request->validate([
            'numero_economico' => 'sometimes|string|max:255',
            'fk_zona_actual' => 'sometimes|nullable|exists:zona,id_zona',
            'estado' => 'sometimes|string|max:50',
            'fk_licencia_requerida' => 'sometimes|nullable|exists:licencia,id_licencia',
        ]);

        $unidad->update($validated);

        return response()->json([
            'ok' => true,
            'unidad' => $unidad
        ]);
    }

    public function destroy($id)
    {
        $unidad = Unidad::find($id);

        if (!$unidad) {
            return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
        }

        $unidad->delete();

        return response()->json([
            'ok' => true,
            'msg' => 'Unidad eliminada correctamente'
        ]);
    }


public function cambiarEstado($id, Request $request)
{
    // Verificar que la unidad exista en la base de datos
    $unidad = Unidad::find($id);

    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    // Validación de estado nuevo
    $request->validate([
        'estado_nuevo' => 'required|string|in:DISPONIBLE,NO_DISPONIBLE,BAJA,MANTENIMIENTO',
        'motivo' => 'required|string',
    ]);

    $estadoAnterior = $unidad->estado;
    $unidad->estado = $request->estado_nuevo;
    $unidad->save();

    // Guardar el historial del cambio de estado
    ReporteUnidad::create([
        'fk_unidad' => $unidad->id_unidad,  // Asegúrate de que 'id_unidad' es el campo correcto en tu base de datos
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo' => $request->estado_nuevo,
        'motivo' => $request->motivo,
        'fecha_reporte' => now(),
    ]);

    return response()->json(['ok' => true, 'msg' => 'Estado actualizado correctamente']);
}
    public function getHistorial($id)
    {
        // Obtener el historial de cambios de estado de la unidad
        $historial = ReporteUnidad::where('fk_unidad', $id)
            ->orderBy('fecha_reporte', 'desc')
            ->get();

        return response()->json(['historial' => $historial]);
    }


  public function getHistorialEstado($id)
{
    $unidad = Unidad::find($id);

    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    // Obtener el historial de cambios de estado
    $historial = ReporteUnidad::where('fk_unidad', $id)->orderBy('fecha_reporte', 'desc')->get();

    return response()->json(['ok' => true, 'historial' => $historial]);
}

public function asignarOperador(Request $request, $id)
{
    $request->validate([
        'id_operador' => 'required|integer|exists:operador,id_operador',
    ]);

    $unidad = Unidad::find($id);
    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    $operador = \App\Models\Operador::find($request->id_operador);
    if (!$operador) {
        return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
    }

    if ($unidad->estado === 'BAJA') {
        return response()->json(['ok' => false, 'msg' => 'La unidad está dada de baja.'], 409);
    }
    if ($unidad->estado === 'EN_VIAJE') {
        return response()->json(['ok' => false, 'msg' => 'La unidad está en viaje activo.'], 409);
    }

    $otroOperador = \App\Models\Operador::where('fk_unidad_asignada', $id)
        ->where('id_operador', '!=', $request->id_operador)
        ->first();

    if ($otroOperador) {
        return response()->json([
            'ok'  => false,
            'msg' => "La unidad ya está asignada a {$otroOperador->nombres} {$otroOperador->apellidos}.",
        ], 409);
    }

    if ($operador->fk_unidad_asignada && $operador->fk_unidad_asignada != $id) {
        DB::table('unidad')
            ->where('id_unidad', $operador->fk_unidad_asignada)
            ->update(['estado' => 'DISPONIBLE']);
    }

    return DB::transaction(function () use ($operador, $id) {
        DB::table('operador')
            ->where('id_operador', $operador->id_operador)
            ->update(['fk_unidad_asignada' => $id]);

        // ✅ Registrar en historial
        UnidadHistorialOperador::create([
            'fk_unidad'      => $id,
            'fk_operador'    => $operador->id_operador,
            'fk_coordinador' => \Illuminate\Support\Facades\Auth::id(),
            'tipo'           => 'ASIGNACION',
            'motivo'         => null,
            'created_at'     => now(),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Operador asignado correctamente']);
    });
}



public function quitarOperador($id)
{
    $unidad = Unidad::find($id);
    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    $operador = \App\Models\Operador::where('fk_unidad_asignada', $id)->first();
    if (!$operador) {
        return response()->json(['ok' => false, 'msg' => 'Esta unidad no tiene operador asignado.'], 404);
    }

    // ✅ Bloquear si está EN_VIAJE o ASIGNADO
    if (in_array($operador->estado_operador, ['EN_VIAJE', 'ASIGNADO'])) {
        return response()->json([
            'ok'  => false,
            'msg' => $operador->estado_operador === 'EN_VIAJE'
                ? 'El operador está en viaje activo. Finaliza el viaje antes de quitar la unidad.'
                : 'El operador tiene un viaje asignado. Cancela o reasigna el viaje antes de quitar la unidad.',
        ], 409);
    }

    return DB::transaction(function () use ($operador, $id) {
        DB::table('operador')
            ->where('id_operador', $operador->id_operador)
            ->update(['fk_unidad_asignada' => null]);

        UnidadHistorialOperador::create([
            'fk_unidad'      => $id,
            'fk_operador'    => $operador->id_operador,
            'fk_coordinador' => \Illuminate\Support\Facades\Auth::id(),
            'tipo'           => 'BAJA',
            'motivo'         => null,
            'created_at'     => now(),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Operador removido de la unidad correctamente']);
    });
}

public function historialOperadores(Request $request, $id)
{
    $query = DB::table('unidad_historial_operador as uho')
        ->leftJoin('operador as op', 'op.id_operador', '=', 'uho.fk_operador')
        ->leftJoin('usuarios as u',  'u.idUsuario',    '=', 'uho.fk_coordinador')
        ->where('uho.fk_unidad', $id)
        ->select([
            'uho.id',
            'uho.tipo',
            'uho.motivo',
            'uho.created_at',
            'op.id_operador',
            'op.numero_empleado',
            DB::raw("CONCAT(op.nombres, ' ', op.apellidos) as nombre_operador"),
            DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
        ]);

    if ($request->filled('tipo')) {
        $query->where('uho.tipo', $request->input('tipo'));
    }
    if ($request->filled('fecha_desde')) {
        $query->whereDate('uho.created_at', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('uho.created_at', '<=', $request->input('fecha_hasta'));
    }

    $query->orderBy('uho.created_at', 'desc');

    $hayFiltros = $request->filled('tipo')
        || $request->filled('fecha_desde')
        || $request->filled('fecha_hasta');

    if (!$hayFiltros) $query->limit(10);

    $historial = $query->get();

    return response()->json([
        'ok'        => true,
        'historial' => $historial,
        'filtrado'  => $hayFiltros,
        'total'     => $historial->count(),
    ]);
}



public function historialZona(Request $request, $id)
{
    $query = DB::table('unidad_historial_zona as uhz')
        ->leftJoin('zona as za', 'za.id_zona', '=', 'uhz.zona_anterior')
        ->leftJoin('zona as zn', 'zn.id_zona', '=', 'uhz.zona_nueva')
        ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'uhz.fk_coordinador')
        ->where('uhz.fk_unidad', $id)
        ->select([
            'uhz.id',
            'uhz.motivo',
            'uhz.created_at',
            'za.nombre_zona as zona_anterior',
            'zn.nombre_zona as zona_nueva',
            DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
        ]);

    if ($request->filled('zona_anterior')) {
        $query->where('za.nombre_zona', $request->input('zona_anterior'));
    }
    if ($request->filled('zona_nueva')) {
        $query->where('zn.nombre_zona', $request->input('zona_nueva'));
    }
    if ($request->filled('fecha_desde')) {
        $query->whereDate('uhz.created_at', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('uhz.created_at', '<=', $request->input('fecha_hasta'));
    }

    $query->orderBy('uhz.created_at', 'desc');

    $hayFiltros = $request->filled('zona_anterior')
        || $request->filled('zona_nueva')
        || $request->filled('fecha_desde')
        || $request->filled('fecha_hasta');

    if (!$hayFiltros) $query->limit(7);

    $historial = $query->get();

    return response()->json([
        'ok'        => true,
        'historial' => $historial,
        'filtrado'  => $hayFiltros,
        'total'     => $historial->count(),
    ]);
}

public function cambiarZona(Request $request, $id)
{
    $request->validate([
        'zona_nueva' => 'required|integer|exists:zona,id_zona',
        'motivo'     => 'required|string|max:255',
    ]);

    $unidad = Unidad::find($id);
    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    $zonaAnterior = $unidad->fk_zona_actual;
    $zonaNueva    = (int) $request->input('zona_nueva');

    return DB::transaction(function () use ($unidad, $zonaAnterior, $zonaNueva, $request) {
        if ((int)$zonaAnterior !== $zonaNueva) {
            \App\Models\UnidadHistorialZona::create([
                'fk_unidad'      => $unidad->id_unidad,
                'fk_coordinador' => \Illuminate\Support\Facades\Auth::id(),
                'fk_viaje'       => null,
                'zona_anterior'  => $zonaAnterior,
                'zona_nueva'     => $zonaNueva,
                'motivo'         => $request->input('motivo'),
                'created_at'     => now(),
            ]);
        }

        DB::table('unidad')
            ->where('id_unidad', $unidad->id_unidad)
            ->update(['fk_zona_actual' => $zonaNueva]);

        return response()->json([
            'ok'  => true,
            'msg' => 'Zona actualizada correctamente',
            'data' => [
                'zona_anterior' => $zonaAnterior,
                'zona_nueva'    => $zonaNueva,
            ]
        ]);
    });
}
public function historialEstadoFiltrado(Request $request, $id)
{
    $query = DB::table('reporte_unidad')
        ->where('fk_unidad', $id)
      ->select([
    'id_reporte as id',  // ← corrige aquí
    'estado_anterior',
    'estado_nuevo',
    'motivo',
    'fecha_reporte as created_at',
]);

    if ($request->filled('estado_anterior')) {
        $query->where('estado_anterior', $request->input('estado_anterior'));
    }
    if ($request->filled('estado_nuevo')) {
        $query->where('estado_nuevo', $request->input('estado_nuevo'));
    }
    if ($request->filled('fecha_desde')) {
        $query->whereDate('fecha_reporte', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('fecha_reporte', '<=', $request->input('fecha_hasta'));
    }

    $query->orderBy('fecha_reporte', 'desc');

    $hayFiltros = $request->filled('estado_anterior')
        || $request->filled('estado_nuevo')
        || $request->filled('fecha_desde')
        || $request->filled('fecha_hasta');

    if (!$hayFiltros) $query->limit(7);

    $historial = $query->get();

    return response()->json([
        'ok'        => true,
        'historial' => $historial,
        'filtrado'  => $hayFiltros,
        'total'     => $historial->count(),
    ]);
}

public function showConDetalle($id)
{
    $unidad = Unidad::with(['zona', 'licencia'])->find($id);

    if (!$unidad) {
        return response()->json(['ok' => false, 'msg' => 'Unidad no encontrada'], 404);
    }

    $operadorAsignado = DB::table('operador')
        ->where('fk_unidad_asignada', $id)
        ->select('id_operador', 'nombres', 'apellidos', 'numero_empleado', 'estado_operador')
        ->first();

    return response()->json([
        'ok'               => true,
        'unidad'           => $unidad,
        'operador_asignado' => $operadorAsignado,
    ]);
}
}