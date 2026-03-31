<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadHistorialZona extends Model
{
    public $timestamps    = false;
    protected $table      = 'unidad_historial_zona';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fk_unidad',
        'fk_coordinador',
        'fk_viaje',
        'zona_anterior',
        'zona_nueva',
        'motivo',
        'created_at',
    ];

    public function unidad()      { return $this->belongsTo(Unidad::class,  'fk_unidad',       'id_unidad'); }
    public function coordinador() { return $this->belongsTo(Usuario::class, 'fk_coordinador',  'idUsuario'); }
    public function viaje()       { return $this->belongsTo(Viaje::class,   'fk_viaje',        'id_viaje'); }
}