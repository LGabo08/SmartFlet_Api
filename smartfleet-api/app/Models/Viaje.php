<?php
// app/Models/Viaje.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viaje extends Model
{
    use HasFactory;

    protected $table = 'viaje';
    protected $primaryKey = 'id_viaje';
    public $timestamps = false;

    protected $fillable = [
        'numero_viaje',
        'fk_ruta',
        'fk_licencia_requerida',
        'fk_operador',
        'fk_unidad',
        'fecha_salida',
        'fecha_llegada',
        'estado',
        'pago_operador',
        'configuracion_unidad', // Nuevo campo
        'cliente',              // Nuevo campo
        'producto',             // Nuevo campo
    ];

    public function operador()
    {
        return $this->belongsTo(\App\Models\Operador::class, 'fk_operador', 'id_operador');
    }

    public function ruta()
    {
        return $this->belongsTo(\App\Models\Ruta::class, 'fk_ruta', 'id_ruta');
    }

    public function licencia()
    {
        return $this->belongsTo(\App\Models\Licencia::class, 'fk_licencia_requerida', 'id_licencia');
    }

    public function certificaciones()
    {
        return $this->belongsToMany(
            \App\Models\Certificacion::class,
            'viaje_certificacion',
            'fk_viaje',
            'fk_certificacion'
        )->withPivot('obligatoria');
    }

    public function unidad()
    {
        return $this->belongsTo(\App\Models\Unidad::class, 'fk_unidad', 'id_unidad');
    }

    public function rechazosOperador()
    {
        return $this->hasMany(\App\Models\RechazoOperador::class, 'fk_viaje', 'id_viaje');
    }
}