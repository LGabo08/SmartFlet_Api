<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SincronizacionControlService;
use App\Services\SincronizarRutasHesaService;
use Illuminate\Http\JsonResponse;

class SincronizacionController extends Controller
{
    // ── Se llama automáticamente cuando alguien entra a la app ──────────────
    public function verificar(SincronizacionControlService $control): JsonResponse
    {
        $despachado = $control->verificarYSincronizar();
        $ultima     = $control->ultimaSincronizacion();

        return response()->json([
            'ok'          => true,
            'despachado'  => $despachado,
            'mensaje'     => $despachado
                ? 'Sincronización iniciada en background'
                : 'No es necesario sincronizar aún',
            'ultima_sincronizacion' => $ultima?->ejecutado_at,
        ]);
    }

    // ── Forzar sincronización manual desde el panel ──────────────────────────
    public function sincronizarManual(SincronizarRutasHesaService $service): JsonResponse
    {
        try {
            $stats = $service->sincronizar();

            return response()->json([
                'ok'      => true,
                'mensaje' => 'Sincronización completada',
                'stats'   => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}