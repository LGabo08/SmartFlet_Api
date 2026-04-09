<?php

namespace App\Jobs;

use App\Services\SincronizarRutasHesaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarRutasHesaJob implements ShouldQueue
{
    use Queueable;

    public function handle(SincronizarRutasHesaService $service): void
    {
        Log::info('[SincronizarRutasHesaJob] Iniciando sincronización en background...');

        $stats = $service->sincronizar();

        // Actualizar registro en tabla sincronizaciones
        DB::table('sincronizaciones')
            ->where('tipo', 'rutas_hesa')
            ->update([
                'ejecutado_at'   => now(),
                'zonas_creadas'  => $stats['zonas_creadas'],
                'rutas_creadas'  => $stats['rutas_creadas'],
                'rutas_omitidas' => $stats['rutas_omitidas'],
                'errores'        => !empty($stats['errores'])
                    ? json_encode($stats['errores'])
                    : null,
                'updated_at'     => now(),
            ]);

        Log::info('[SincronizarRutasHesaJob] Sincronización completada.', $stats);
    }
}