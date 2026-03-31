<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViajeFinalizacion extends Model
{
    protected $table      = 'viaje_finalizacion';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    protected $fillable = [
        'fk_viaje',
        'fk_coordinador',
        'tipo_finalizacion',
        'notas',
        'fecha_llegada_real',
        'fecha_finalizacion',
        'created_at',
    ];
}