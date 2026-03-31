<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadHistorialOperador extends Model
{
    protected $table      = 'unidad_historial_operador';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    protected $fillable = [
        'fk_unidad',
        'fk_operador',
        'fk_coordinador',
        'tipo',
        'motivo',
        'created_at',
    ];
}