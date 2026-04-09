<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarRutasHesaService
{
    public function sincronizar(): array
    {
        $stats = [
            'zonas_creadas'   => 0,
            'rutas_creadas'   => 0,
            'rutas_omitidas'  => 0,
            'errores'         => [],
        ];

        try {
            // 1. Leer la vista de Oracle
            $rutasOracle = DB::connection('oracle')
                ->table('INFOFIN.TRN_FH_RUTAS_VIAJES_HESA')
                ->select('id_ruta', 'nombre_ruta', 'kilometros', 'municipio_origen', 'municipio_destino')
                ->get();

        } catch (\Exception $e) {
            Log::error('[SincronizarRutas] Error al conectar con Oracle: ' . $e->getMessage());
            throw $e;
        }

        foreach ($rutasOracle as $row) {
            try {
                // 2. Buscar o crear zona ORIGEN
                $idOrigen = $this->obtenerOCrearZona($row->municipio_origen, $stats);

                // 3. Buscar o crear zona DESTINO
                $idDestino = $this->obtenerOCrearZona($row->municipio_destino, $stats);

                // 4. Insertar ruta si no existe (validamos por nombre_ruta)
                $existe = DB::table('ruta')
                    ->where('nombre_ruta', $row->nombre_ruta)
                    ->exists();

                if (!$existe) {
                    DB::table('ruta')->insert([
                        'nombre_ruta'     => $row->nombre_ruta,
                        'fk_zona_origen'  => $idOrigen,
                        'fk_zona_destino' => $idDestino,
                        'distancia_km'    => $row->kilometros,
                    ]);
                    $stats['rutas_creadas']++;
                    Log::info("[SincronizarRutas] Ruta creada: {$row->nombre_ruta}");
                } else {
                    $stats['rutas_omitidas']++;
                }

            } catch (\Exception $e) {
                $stats['errores'][] = "Ruta [{$row->nombre_ruta}]: " . $e->getMessage();
                Log::error("[SincronizarRutas] Error en ruta {$row->nombre_ruta}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Busca la zona por nombre; si no existe la crea y devuelve el id
    // ─────────────────────────────────────────────────────────────────────────
    private function obtenerOCrearZona(string $nombreZona, array &$stats): int
    {
        $zona = DB::table('zona')
            ->where('nombre_zona', $nombreZona)
            ->first();

        if ($zona) {
            return $zona->id_zona;
        }

        $id = DB::table('zona')->insertGetId([
            'nombre_zona' => $nombreZona,
        ]);

        $stats['zonas_creadas']++;
        Log::info("[SincronizarRutas] Zona creada: {$nombreZona}");

        return $id;
    }
}