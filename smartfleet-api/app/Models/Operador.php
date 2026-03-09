<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operador extends Model
{
    use HasFactory;

    protected $table = 'operador';
    protected $primaryKey = 'id_operador';
    public $timestamps = false;

    protected $fillable = [
        'numero_empleado',
        'nombres',
        'apellidos',
        'fk_zona_actual',
        'fk_tipo_licencia',
        'vigencia_licencia',
        'estado_operador',
        'fk_unidad_asignada',
    ];

    // Un operador puede tener muchos viajes
    public function viajes()
    {
        return $this->hasMany(Viaje::class, 'fk_operador', 'id_operador');
    }

    // Zona actual del operador
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_actual', 'id_zona');
    }

    // Unidad asignada del operador
    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'fk_unidad_asignada', 'id_unidad');
    }

    // Tipo de licencia
    public function licencia()
    {
        return $this->belongsTo(Licencia::class, 'fk_tipo_licencia', 'id_licencia');
    }

    /**
     * ✅ Operador tiene MUCHAS certificaciones (N:M) mediante operador_certificacion
     */
    public function certificaciones()
    {
        return $this->belongsToMany(
            \App\Models\Certificacion::class,
            'operador_certificacion',
            'fk_operador',
            'fk_certificacion'
        )->withPivot('fecha_obtencion');
    }
}