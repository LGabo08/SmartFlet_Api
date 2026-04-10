<?php

namespace App\Jobs;

use App\Services\SincronizarClientesHesaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarClientesHesaJob implements ShouldQueue
{
    use Queueable;

    public function handle(SincronizarClientesHesaService $service): void
    {
        Log::info('[SincronizarClientesHesaJob] Iniciando...');

        $stats = $service->sincronizar();

        DB::table('sincronizaciones')
            ->where('tipo', 'clientes_hesa')
            ->update([
                'ejecutado_at'          => now(),
                'clientes_creados'      => $stats['clientes_creados'],
                'clientes_actualizados' => $stats['clientes_actualizados'],
                'errores'               => !empty($stats['errores'])
                    ? json_encode($stats['errores'])
                    : null,
                'updated_at'            => now(),
            ]);

        Log::info('[SincronizarClientesHesaJob] Completado.', $stats);
    }
}