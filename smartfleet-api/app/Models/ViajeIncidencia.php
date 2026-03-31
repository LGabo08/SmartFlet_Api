<?php
// app/Models/ViajeIncidencia.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViajeIncidencia extends Model
{
    public $timestamps = false;
    protected $table      = 'viaje_incidencia';
    protected $primaryKey = 'id_incidencia';

    protected $fillable = [
        'fk_viaje',
        'fk_operador',
        'fk_coordinador',
        'tipo_evento',
        'adv_unidad_no_disponible',
        'adv_licencia_vencida',
        'adv_licencia_incorrecta',
        'adv_operador_fuera_zona',
        'adv_unidad_fuera_zona',
        'adv_cuota_agotada',
        'adv_certificaciones_faltantes',
        'detalle',
        'created_at',
    ];

    public function viaje()
    {
        return $this->belongsTo(Viaje::class, 'fk_viaje', 'id_viaje');
    }

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'fk_operador', 'id_operador');
    }

    public function coordinador()
    {
        return $this->belongsTo(Usuario::class, 'fk_coordinador', 'idUsuario');
    }
}