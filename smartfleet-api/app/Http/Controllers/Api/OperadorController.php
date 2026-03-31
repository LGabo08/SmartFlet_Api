<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperadorController extends Controller
{
    public function index()
    {
        $operadores = Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])->get();

        return response()->json([
            'ok' => true,
            'operadores' => $operadores
        ]);
    }

  public function show($id)
{
    $operador = Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])->find($id);

    if (!$operador) {
        return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
    }

    // Info extra de la unidad asignada
    $unidadInfo = null;
    if ($operador->fk_unidad_asignada) {
        $unidadInfo = DB::table('unidad as u')
            ->leftJoin('operador as op', 'op.fk_unidad_asignada', '=', 'u.id_unidad')
            ->where('u.id_unidad', $operador->fk_unidad_asignada)
            ->select([
                'u.id_unidad',
                'u.numero_economico',
                'u.estado',
                'u.fk_zona_actual',
                DB::raw("CASE WHEN op.id_operador IS NOT NULL AND op.id_operador != {$operador->id_operador} 
                         THEN op.id_operador ELSE NULL END as otro_operador_id"),
                DB::raw("CASE WHEN op.id_operador IS NOT NULL AND op.id_operador != {$operador->id_operador} 
                         THEN CONCAT(op.nombres, ' ', op.apellidos) ELSE NULL END as otro_operador_nombre"),
            ])
            ->first();
    }

    return response()->json([
        'ok'         => true,
        'operador'   => $operador,
        'unidad_info' => $unidadInfo,
    ]);
}

    public function store(Request $request)
    {
$validatedData = $request->validate([
    'numero_empleado' => 'required|string|max:255',
    'nombres' => 'required|string|max:255',
    'apellidos' => 'required|string|max:255',

    'fk_zona_actual' => 'nullable|exists:zona,id_zona',  // Cambiar a nullable
    'fk_unidad_asignada' => 'nullable|exists:unidad,id_unidad', // Asegurarse de que 'unidad' también sea nullable si es opcional

    'estado_operador' => 'required|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO,EN_VIAJE',

    'fk_tipo_licencia' => 'nullable|exists:licencia,id_licencia', // Cambiar a nullable
    'vigencia_licencia' => 'nullable|date',  // Cambiar a nullable

    'certificaciones' => 'sometimes|array',  // Certificaciones no es obligatorio, solo se pasa si viene en el payload
    'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
]);
        // separar certs del payload para no romper mass-assignment
        $certIds = $validatedData['certificaciones'] ?? [];
        unset($validatedData['certificaciones']);

        return DB::transaction(function () use ($validatedData, $certIds) {
            $operador = Operador::create($validatedData);

            // ✅ asignar certificaciones (si vienen)
            if (!empty($certIds)) {
                $operador->certificaciones()->sync($certIds);
            }

            return response()->json([
                'ok' => true,
                'operador' => Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])
                    ->find($operador->id_operador)
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $operador = Operador::find($id);

        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

 $validatedData = $request->validate([
    'numero_empleado' => 'required|string|max:255',
    'nombres' => 'required|string|max:255',
    'apellidos' => 'required|string|max:255',

    'fk_zona_actual' => 'nullable|exists:zona,id_zona',  // Cambiar a nullable
    'fk_unidad_asignada' => 'nullable|exists:unidad,id_unidad', // Asegurarse de que 'unidad' también sea nullable si es opcional

    'estado_operador' => 'required|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO,EN_VIAJE',

    'fk_tipo_licencia' => 'nullable|exists:licencia,id_licencia', // Cambiar a nullable
    'vigencia_licencia' => 'nullable|date',  // Cambiar a nullable

    'certificaciones' => 'sometimes|array',  // Certificaciones no es obligatorio, solo se pasa si viene en el payload
    'certificaciones.*' => 'integer|exists:certificacion,id_certificacion',
]);

        $certIds = $validatedData['certificaciones'] ?? null;
        unset($validatedData['certificaciones']);

        return DB::transaction(function () use ($operador, $validatedData, $certIds) {
            // update campos del operador
            if (!empty($validatedData)) {
                $operador->update($validatedData);
            }

            // ✅ si el front manda certificaciones, hacemos sync (reemplaza)
            if (is_array($certIds)) {
                $operador->certificaciones()->sync($certIds);
            }

            return response()->json([
                'ok' => true,
                'operador' => Operador::with(['zona', 'unidad', 'licencia', 'certificaciones'])
                    ->find($operador->id_operador)
            ]);
        });
    }

    public function destroy($id)
    {
        $operador = Operador::find($id);

        if (!$operador) {
            return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
        }

        // ✅ opcional: al borrar operador, limpia pivote (si no tienes ON DELETE CASCADE)
        $operador->certificaciones()->detach();

        $operador->delete();

        return response()->json(['ok' => true, 'msg' => 'Operador eliminado']);
    }

    // En OperadorController.php
public function getLicencias()
{
    // Obtener todas las licencias
    $licencias = Licencia::all();

    return response()->json([
        'ok' => true,
        'licencias' => $licencias
    ]);
}




public function historialEstado(Request $request, $id)
{
    $query = DB::table('operador_historial_estado as ohe')
        ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'ohe.fk_coordinador')
        ->where('ohe.fk_operador', $id)
        ->select([
            'ohe.id',
            'ohe.estado_anterior',
            'ohe.estado_nuevo',
            'ohe.motivo',
            'ohe.created_at',
            DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
        ]);

    // Filtro estado anterior
    if ($request->filled('estado_anterior')) {
        $query->where('ohe.estado_anterior', $request->input('estado_anterior'));
    }

    // Filtro estado nuevo
    if ($request->filled('estado_nuevo')) {
        $query->where('ohe.estado_nuevo', $request->input('estado_nuevo'));
    }

    // Filtro rango de fechas
    if ($request->filled('fecha_desde')) {
        $query->whereDate('ohe.created_at', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('ohe.created_at', '<=', $request->input('fecha_hasta'));
    }

    $query->orderBy('ohe.created_at', 'desc');

    // Si no hay filtros activos, limita a 7
    $hayFiltros = $request->filled('estado_anterior')
        || $request->filled('estado_nuevo')
        || $request->filled('fecha_desde')
        || $request->filled('fecha_hasta');

    if (!$hayFiltros) {
        $query->limit(7);
    }

    $historial = $query->get();

    return response()->json([
        'ok'         => true,
        'historial'  => $historial,
        'filtrado'   => $hayFiltros,
        'total'      => $historial->count(),
    ]);
}

public function historialZona(Request $request, $id)
{
    $query = DB::table('operador_historial_zona as ohz')
        ->leftJoin('zona as za', 'za.id_zona', '=', 'ohz.zona_anterior')
        ->leftJoin('zona as zn', 'zn.id_zona', '=', 'ohz.zona_nueva')
        ->leftJoin('usuarios as u', 'u.idUsuario', '=', 'ohz.fk_coordinador')
        ->where('ohz.fk_operador', $id)
        ->select([
            'ohz.id',
            'ohz.motivo',
            'ohz.created_at',
            'za.nombre_zona as zona_anterior',
            'zn.nombre_zona as zona_nueva',
            DB::raw("CONCAT(u.nombre, ' ', u.apellidos) as nombre_coordinador"),
        ]);

    // Filtro zona anterior
 if ($request->filled('zona_anterior')) {
    $query->where('za.nombre_zona', $request->input('zona_anterior'));
}
    // Filtro zona nueva
    if ($request->filled('zona_nueva')) {
    $query->where('zn.nombre_zona', $request->input('zona_nueva'));
}

    // Filtro rango de fechas
    if ($request->filled('fecha_desde')) {
        $query->whereDate('ohz.created_at', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('ohz.created_at', '<=', $request->input('fecha_hasta'));
    }

    $query->orderBy('ohz.created_at', 'desc');

    $hayFiltros = $request->filled('zona_anterior')
        || $request->filled('zona_nueva')
        || $request->filled('fecha_desde')
        || $request->filled('fecha_hasta');

    if (!$hayFiltros) {
        $query->limit(7);
    }

    $historial = $query->get();

    return response()->json([
        'ok'        => true,
        'historial' => $historial,
        'filtrado'  => $hayFiltros,
        'total'     => $historial->count(),
    ]);
}
public function cambiarEstado(Request $request, $id)
{
    $request->validate([
        'estado_nuevo' => 'required|in:DISPONIBLE,NO_DISPONIBLE,INACTIVO,EN_VIAJE',
        'motivo'       => 'required|string|max:255',
    ]);

    $operador = Operador::find($id);
    if (!$operador) {
        return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
    }

    // ✅ Bloquear si está ASIGNADO o EN_VIAJE
    if (in_array($operador->estado_operador, ['ASIGNADO', 'EN_VIAJE'])) {
        return response()->json([
            'ok'  => false,
            'msg' => $operador->estado_operador === 'EN_VIAJE'
                ? 'No se puede cambiar el estado del operador mientras está en viaje activo.'
                : 'No se puede cambiar el estado del operador mientras tiene un viaje asignado.',
        ], 409);
    }

    $estadoAnterior = $operador->estado_operador;
    $estadoNuevo    = $request->input('estado_nuevo');

    return DB::transaction(function () use ($operador, $estadoAnterior, $estadoNuevo, $request) {
        if ($estadoAnterior !== $estadoNuevo) {
            \App\Models\OperadorHistorialEstado::create([
                'fk_operador'     => $operador->id_operador,
                'fk_coordinador'  => \Illuminate\Support\Facades\Auth::id(),
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $estadoNuevo,
                'motivo'          => $request->input('motivo'),
                'created_at'      => now(),
            ]);
        }

        DB::table('operador')
            ->where('id_operador', $operador->id_operador)
            ->update(['estado_operador' => $estadoNuevo]);

        return response()->json([
            'ok'  => true,
            'msg' => 'Estado actualizado correctamente',
            'data' => [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $estadoNuevo,
            ]
        ]);
    });
}

public function cambiarZona(Request $request, $id)
{
    $request->validate([
        'zona_nueva' => 'required|integer|exists:zona,id_zona',
        'motivo'     => 'required|string|max:255',
    ]);

    $operador = Operador::find($id);
    if (!$operador) {
        return response()->json(['ok' => false, 'msg' => 'Operador no encontrado'], 404);
    }

    // ✅ Bloquear si está ASIGNADO o EN_VIAJE
    if (in_array($operador->estado_operador, ['ASIGNADO', 'EN_VIAJE'])) {
        return response()->json([
            'ok'  => false,
            'msg' => $operador->estado_operador === 'EN_VIAJE'
                ? 'No se puede cambiar la zona del operador mientras está en viaje activo.'
                : 'No se puede cambiar la zona del operador mientras tiene un viaje asignado.',
        ], 409);
    }

    $zonaAnterior = $operador->fk_zona_actual;
    $zonaNueva    = (int) $request->input('zona_nueva');

    return DB::transaction(function () use ($operador, $zonaAnterior, $zonaNueva, $request) {
        if ((int)$zonaAnterior !== $zonaNueva) {
            \App\Models\OperadorHistorialZona::create([
                'fk_operador'    => $operador->id_operador,
                'fk_coordinador' => \Illuminate\Support\Facades\Auth::id(),
                'fk_viaje'       => null,
                'zona_anterior'  => $zonaAnterior,
                'zona_nueva'     => $zonaNueva,
                'motivo'         => $request->input('motivo'),
                'created_at'     => now(),
            ]);
        }

        DB::table('operador')
            ->where('id_operador', $operador->id_operador)
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
}