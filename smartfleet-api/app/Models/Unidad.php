<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory;

    // Definir la tabla de la base de datos
    protected $table = 'unidad';

    // Definir la clave primaria
    protected $primaryKey = 'id_unidad';

    // Indicar que la columna 'id_unidad' no es autoincremental
    public $incrementing = false;

    // Definir los campos que se pueden asignar masivamente
    protected $fillable = [
        'numero_economico',
        'fk_zona_actual',
        'estado',
        'fk_licencia_requerida',
    ];

    // Relaciones con otras tablas
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_actual', 'id_zona');
    }

    public function licencia()
    {
        return $this->belongsTo(Licencia::class, 'fk_licencia_requerida', 'id_licencia');
    }

    // Relación con la tabla viajes (si aplica)
    public function viajes()
    {
        return $this->hasMany(Viaje::class, 'fk_unidad');
    }
}