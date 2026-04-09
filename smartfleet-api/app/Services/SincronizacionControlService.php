<?php

namespace App\Services;

use App\Jobs\SincronizarRutasHesaJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SincronizacionControlService
{
    const INTERVALO_HORAS = 6;

    public function verificarYSincronizar(): bool
    {
        $ultima = DB::table('sincronizaciones')
            ->where('tipo', 'rutas_hesa')
            ->first();

        $fechaUltima = Carbon::parse($ultima->ejecutado_at);
        $diff = abs(Carbon::now()->diffInHours($fechaUltima));

        Log::info("[Sincronizacion] Ultima: {$fechaUltima} | Diff horas: {$diff} | Intervalo: " . self::INTERVALO_HORAS);

        if (!$ultima || $diff >= self::INTERVALO_HORAS) {
            SincronizarRutasHesaJob::dispatch();
            return true;
        }

        return false;
    }

    public function ultimaSincronizacion(): object|null
    {
        return DB::table('sincronizaciones')
            ->where('tipo', 'rutas_hesa')
            ->first();
    }
}