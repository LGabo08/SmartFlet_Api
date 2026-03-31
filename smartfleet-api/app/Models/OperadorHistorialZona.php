<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperadorHistorialZona extends Model
{
    public $timestamps    = false;
    protected $table      = 'operador_historial_zona';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fk_operador',
        'fk_coordinador',
        'fk_viaje',
        'zona_anterior',
        'zona_nueva',
        'motivo',
        'created_at',
    ];

    public function operador()    { return $this->belongsTo(Operador::class, 'fk_operador',    'id_operador'); }
    public function coordinador() { return $this->belongsTo(Usuario::class,  'fk_coordinador', 'idUsuario'); }
    public function viaje()       { return $this->belongsTo(Viaje::class,    'fk_viaje',       'id_viaje'); }
}