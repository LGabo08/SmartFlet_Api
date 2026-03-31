<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperadorHistorialEstado extends Model
{
    public $timestamps    = false;
    protected $table      = 'operador_historial_estado';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fk_operador',
        'fk_coordinador',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'created_at',
    ];

    public function operador()    { return $this->belongsTo(Operador::class,  'fk_operador',    'id_operador'); }
    public function coordinador() { return $this->belongsTo(Usuario::class,   'fk_coordinador', 'idUsuario'); }
}