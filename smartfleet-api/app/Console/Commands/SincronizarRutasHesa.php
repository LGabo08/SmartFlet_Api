<?php

namespace App\Console\Commands;

use App\Services\SincronizarRutasHesaService;
use Illuminate\Console\Command;

class SincronizarRutasHesa extends Command
{
    protected $signature   = 'hesa:sincronizar-rutas';
    protected $description = 'Sincroniza rutas desde la vista Oracle de HESA hacia MySQL';

    public function handle(SincronizarRutasHesaService $service): int
    {
        $this->info('');
        $this->info('======================================');
        $this->info('  Sincronización de Rutas HESA');
        $this->info('======================================');

        try {
            $stats = $service->sincronizar();

            $this->info('');
            $this->info('✅  Zonas creadas:   ' . $stats['zonas_creadas']);
            $this->info('✅  Rutas creadas:   ' . $stats['rutas_creadas']);
            $this->warn('⏭️   Rutas omitidas:  ' . $stats['rutas_omitidas']);

            if (!empty($stats['errores'])) {
                $this->error('');
                $this->error('❌  Errores encontrados:');
                foreach ($stats['errores'] as $error) {
                    $this->error('    - ' . $error);
                }
            }

            $this->info('');
            $this->info('Sincronización finalizada.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌  Error crítico: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}