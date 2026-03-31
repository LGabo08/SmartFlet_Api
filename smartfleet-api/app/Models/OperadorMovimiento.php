<?php
// app/Models/OperadorMovimiento.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperadorMovimiento extends Model
{
    public $timestamps  = false;
    protected $table    = 'operador_movimiento';
    protected $primaryKey = 'id_movimiento';

    protected $fillable = [
        'fk_operador',
        'fk_viaje',
        'fk_coordinador',
        'periodo',
        'tipo',
        'monto',
        'descripcion',
        'created_at',
    ];

    public function operador()    { return $this->belongsTo(Operador::class,  'fk_operador',    'id_operador'); }
    public function viaje()       { return $this->belongsTo(Viaje::class,     'fk_viaje',       'id_viaje'); }
    public function coordinador() { return $this->belongsTo(Usuario::class,   'fk_coordinador', 'idUsuario'); }
}