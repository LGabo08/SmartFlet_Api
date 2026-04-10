<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarClientesHesaService
{
    public function sincronizar(): array
    {
        $stats = [
            'clientes_creados'      => 0,
            'clientes_actualizados' => 0,
            'errores'               => [],
        ];

        try {
            $clientesOracle = DB::connection('oracle')
                ->table('INFOFIN.TRN_FH_CFG_CLIENTES_HESA')
                ->select('id_cliente', 'nombre_cliente')
                ->get();

        } catch (\Exception $e) {
            Log::error('[SincronizarClientes] Error al conectar con Oracle: ' . $e->getMessage());
            throw $e;
        }

        foreach ($clientesOracle as $row) {
            try {
                $existe = DB::table('cliente')
                    ->where('id_cliente', $row->id_cliente)
                    ->first();

                if (!$existe) {
                    DB::table('cliente')->insert([
                        'id_cliente'     => $row->id_cliente,
                        'nombre_cliente' => $row->nombre_cliente,
                        'activo'         => 1,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                    $stats['clientes_creados']++;

                } elseif ($existe->nombre_cliente !== $row->nombre_cliente) {
                    DB::table('cliente')
                        ->where('id_cliente', $row->id_cliente)
                        ->update([
                            'nombre_cliente' => $row->nombre_cliente,
                            'updated_at'     => now(),
                        ]);
                    $stats['clientes_actualizados']++;
                }

            } catch (\Exception $e) {
                $stats['errores'][] = "Cliente [{$row->id_cliente}]: " . $e->getMessage();
                Log::error("[SincronizarClientes] Error en cliente {$row->id_cliente}: " . $e->getMessage());
            }
        }

        return $stats;
    }
}