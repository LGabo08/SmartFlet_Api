<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RechazoOperador extends Model
{
    protected $table = 'rechazos_operador';
    public $timestamps = true;

    protected $fillable = [
        'fk_viaje',
        'fk_operador',
        'motivos',
    ];
}