<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class SincronizarPermisos extends Command
{
    protected $signature   = 'permisos:sincronizar';
    protected $description = 'Sincroniza los permisos del sistema con las rutas registradas en api.php';

    // Mapa ruta → permiso
    // Formato: 'METHOD /prefijo/{param}/accion' => ['clave', 'modulo']
    // Agrega aquí las rutas nuevas que vayas creando
    private array $mapa = [
        // Panel
        'GET /panel/resumen'                         => ['ver_panel',               'panel'],

        // Zonas
        'GET /zonas'                                 => ['ver_zonas',               'zonas'],
        'GET /zonas/{id}'                            => ['ver_zonas',               'zonas'],
        'POST /zonas'                                => ['crear_zonas',             'zonas'],
        'PUT /zonas/{id}'                            => ['editar_zonas',            'zonas'],
        'DELETE /zonas/{id}'                         => ['eliminar_zonas',          'zonas'],

        // Clientes
        'GET /clientes'                              => ['ver_clientes',            'clientes'],
        'GET /clientes/selector'                     => ['ver_clientes',            'clientes'],

        // Licencias
        'GET /licencias'                             => ['ver_licencias',           'licencias'],
        'POST /licencias'                            => ['crear_licencias',         'licencias'],
        'PUT /licencias/{licencia}'                  => ['editar_licencias',        'licencias'],
        'DELETE /licencias/{licencia}'               => ['eliminar_licencias',      'licencias'],

        // Rutas
        'GET /rutas'                                 => ['ver_rutas',               'rutas'],
        'POST /rutas'                                => ['crear_rutas',             'rutas'],
        'PUT /rutas/{ruta}'                          => ['editar_rutas',            'rutas'],
        'DELETE /rutas/{ruta}'                       => ['eliminar_rutas',          'rutas'],

        // Certificaciones
        'GET /certificaciones'                       => ['ver_certificaciones',     'certificaciones'],
        'POST /certificaciones'                      => ['crear_certificaciones',   'certificaciones'],
        'PUT /certificaciones/{certificacion}'       => ['editar_certificaciones',  'certificaciones'],
        'DELETE /certificaciones/{certificacion}'    => ['eliminar_certificaciones','certificaciones'],

        // Operadores
        'GET /operadores'                            => ['ver_operadores',          'operadores'],
        'GET /operadores/{id}'                       => ['ver_operadores',          'operadores'],
        'POST /operadores'                           => ['crear_operadores',        'operadores'],
        'PUT /operadores/{id}'                       => ['editar_operadores',       'operadores'],
        'DELETE /operadores/{id}'                    => ['eliminar_operadores',     'operadores'],
        'POST /operadores/{id}/cambiar-estado'       => ['cambiar_estado_operador', 'operadores'],
        'POST /operadores/{id}/cambiar-zona'         => ['cambiar_zona_operador',   'operadores'],
        'GET /operadores/{id}/historial-estado'      => ['ver_historial_operador',  'operadores'],
        'GET /operadores/{id}/historial-zona'        => ['ver_historial_operador',  'operadores'],
        'GET /operadores/{id}/movimientos'           => ['ver_historial_operador',  'operadores'],

        // Unidades
        'GET /unidades'                              => ['ver_unidades',            'unidades'],
        'GET /unidades/{id}'                         => ['ver_unidades',            'unidades'],
        'GET /unidades/{id}/detalle'                 => ['ver_unidades',            'unidades'],
        'POST /unidades'                             => ['crear_unidades',          'unidades'],
        'PUT /unidades/{id}'                         => ['editar_unidades',         'unidades'],
        'DELETE /unidades/{id}'                      => ['eliminar_unidades',       'unidades'],
        'PUT /unidades/{id}/cambiar-estado'          => ['cambiar_estado_unidad',   'unidades'],
        'POST /unidades/{id}/cambiar-zona'           => ['cambiar_zona_unidad',     'unidades'],
        'POST /unidades/{id}/asignar-operador'       => ['asignar_operador_unidad', 'unidades'],
        'POST /unidades/{id}/quitar-operador'        => ['asignar_operador_unidad', 'unidades'],
        'GET /unidades/{id}/historial-zona'          => ['ver_historial_unidad',    'unidades'],
        'GET /unidades/{id}/historial-estado-filtrado' => ['ver_historial_unidad',  'unidades'],
        'GET /unidades/{id}/historial-operadores'    => ['ver_historial_unidad',    'unidades'],

        // Cuotas
        'GET /operador-cuotas'                       => ['ver_cuotas',             'cuotas'],
        'GET /operador-cuotas/{id}'                  => ['ver_cuotas',             'cuotas'],
        'GET /operadores/{id}/cuotas'                => ['ver_cuotas',             'cuotas'],
        'POST /operador-cuotas'                      => ['crear_cuotas',           'cuotas'],
        'PUT /operador-cuotas/{id}'                  => ['editar_cuotas',          'cuotas'],
        'DELETE /operador-cuotas/{id}'               => ['eliminar_cuotas',        'cuotas'],

        // Viajes
        'GET /viajes'                                => ['ver_viajes',             'viajes'],
        'GET /viajes/{id}'                           => ['ver_viajes',             'viajes'],
        'GET /viajes/pendientes'                     => ['ver_viajes',             'viajes'],
        'POST /viajes'                               => ['crear_viajes',           'viajes'],
        'PUT /viajes/{id}'                           => ['editar_viajes',          'viajes'],
        'DELETE /viajes/{id}'                        => ['eliminar_viajes',        'viajes'],
        'POST /viajes/{id}/aprobar'                  => ['aprobar_viajes',         'viajes'],
        'POST /viajes/{id}/rechazar'                 => ['rechazar_viajes',        'viajes'],
        'POST /viajes/{id}/cancelar'                 => ['cancelar_viajes',        'viajes'],
        'POST /viajes/{id}/iniciar'                  => ['iniciar_viajes',         'viajes'],
        'POST /viajes/{id}/finalizar'                => ['finalizar_viajes',       'viajes'],
        'POST /viajes/{id}/reasignar'                => ['reasignar_viajes',       'viajes'],
        'POST /viajes/{id}/cambiar-tarifa'           => ['cambiar_tarifa_viaje',   'viajes'],
        'POST /viajes/{id}/calcular-asignacion'      => ['aprobar_viajes',         'viajes'],
        'GET /viajes/{id}/historial'                 => ['ver_historial_viaje',    'viajes'],
        'GET /viajes/{id}/finalizacion'              => ['ver_historial_viaje',    'viajes'],
        'GET /viajes/{id}/cadena'                    => ['ver_historial_viaje',    'viajes'],

        // Usuarios
        'GET /usuarios'                              => ['ver_usuarios',           'usuarios'],
        'GET /usuarios/{id}'                         => ['ver_usuarios',           'usuarios'],
        'POST /usuarios'                             => ['crear_usuarios',         'usuarios'],
        'PUT /usuarios/{id}'                         => ['editar_usuarios',        'usuarios'],
        'DELETE /usuarios/{id}'                      => ['eliminar_usuarios',      'usuarios'],

        // Roles
        'GET /roles'                                 => ['ver_roles',              'roles'],
        'POST /roles'                                => ['crear_roles',            'roles'],

        // Sincronizar
        'POST /admin/permisos/sincronizar'           => ['sincronizar_permisos',   'admin'],
    ];

    public function handle(): int
    {
        $this->info('Sincronizando permisos...');

        $insertados = 0;
        $yaExistian = 0;

        // Obtener claves únicas del mapa
        $permisosUnicos = collect($this->mapa)
            ->values()
            ->unique(fn($p) => $p[0])
            ->values();

        foreach ($permisosUnicos as [$clave, $modulo]) {
            $existe = DB::table('permisos')->where('clave', $clave)->exists();

            if (!$existe) {
                DB::table('permisos')->insert([
                    'clave'      => $clave,
                    'modulo'     => $modulo,
                    'descripcion'=> $this->descripciones[$clave] ?? $clave,
                    'created_at' => now(),
                ]);
                $insertados++;
                $this->line("  + Nuevo permiso: <info>{$clave}</info>");
            } else {
                $yaExistian++;
            }
        }

        // Asignar permisos nuevos al ADMIN automáticamente
        if ($insertados > 0) {
            $nuevos = DB::table('permisos')
                ->whereNotIn('id', function ($q) {
                    $q->select('fk_permiso')->from('rol_permiso')->where('fk_rol', 1);
                })->pluck('id');

            foreach ($nuevos as $permisoId) {
                DB::table('rol_permiso')->insertOrIgnore([
                    'fk_rol'     => 1,
                    'fk_permiso' => $permisoId,
                ]);
            }

            $this->info("  ✓ Permisos nuevos asignados al ADMIN automáticamente.");
        }

        $this->info("Listo. {$insertados} nuevos, {$yaExistian} ya existían.");

        return Command::SUCCESS;
    }

    // Descripciones legibles para permisos nuevos
    private array $descripciones = [
        'ver_panel'               => 'Ver resumen del panel',
        'ver_zonas'               => 'Ver zonas',
        'crear_zonas'             => 'Crear zonas',
        'editar_zonas'            => 'Editar zonas',
        'eliminar_zonas'          => 'Eliminar zonas',
        'ver_clientes'            => 'Ver clientes',
        'ver_licencias'           => 'Ver licencias',
        'crear_licencias'         => 'Crear licencias',
        'editar_licencias'        => 'Editar licencias',
        'eliminar_licencias'      => 'Eliminar licencias',
        'ver_rutas'               => 'Ver rutas',
        'crear_rutas'             => 'Crear rutas',
        'editar_rutas'            => 'Editar rutas',
        'eliminar_rutas'          => 'Eliminar rutas',
        'ver_certificaciones'     => 'Ver certificaciones',
        'crear_certificaciones'   => 'Crear certificaciones',
        'editar_certificaciones'  => 'Editar certificaciones',
        'eliminar_certificaciones'=> 'Eliminar certificaciones',
        'ver_operadores'          => 'Ver operadores',
        'crear_operadores'        => 'Crear operadores',
        'editar_operadores'       => 'Editar operadores',
        'eliminar_operadores'     => 'Eliminar operadores',
        'cambiar_estado_operador' => 'Cambiar estado de operador',
        'cambiar_zona_operador'   => 'Cambiar zona de operador',
        'ver_historial_operador'  => 'Ver historial de operador',
        'ver_unidades'            => 'Ver unidades',
        'crear_unidades'          => 'Crear unidades',
        'editar_unidades'         => 'Editar unidades',
        'eliminar_unidades'       => 'Eliminar unidades',
        'cambiar_estado_unidad'   => 'Cambiar estado de unidad',
        'cambiar_zona_unidad'     => 'Cambiar zona de unidad',
        'asignar_operador_unidad' => 'Asignar operador a unidad',
        'ver_historial_unidad'    => 'Ver historial de unidad',
        'ver_cuotas'              => 'Ver cuotas',
        'crear_cuotas'            => 'Crear cuotas',
        'editar_cuotas'           => 'Editar cuotas',
        'eliminar_cuotas'         => 'Eliminar cuotas',
        'ver_viajes'              => 'Ver viajes',
        'crear_viajes'            => 'Crear viajes',
        'editar_viajes'           => 'Editar viajes',
        'eliminar_viajes'         => 'Eliminar viajes',
        'aprobar_viajes'          => 'Aprobar viajes',
        'rechazar_viajes'         => 'Rechazar viajes',
        'cancelar_viajes'         => 'Cancelar viajes',
        'iniciar_viajes'          => 'Iniciar viajes',
        'finalizar_viajes'        => 'Finalizar viajes',
        'reasignar_viajes'        => 'Reasignar viajes',
        'cambiar_tarifa_viaje'    => 'Cambiar tarifa de viaje',
        'ver_historial_viaje'     => 'Ver historial de viaje',
        'ver_usuarios'            => 'Ver usuarios',
        'crear_usuarios'          => 'Crear usuarios',
        'editar_usuarios'         => 'Editar usuarios',
        'eliminar_usuarios'       => 'Eliminar usuarios',
        'ver_roles'               => 'Ver roles',
        'crear_roles'             => 'Crear roles',
        'sincronizar_permisos'    => 'Sincronizar permisos',
    ];
}