<?php

// src/app/Models/Viaje.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viaje extends Model
{
    use HasFactory;

    // Definir la tabla
    protected $table = 'viaje';

    // Definir los campos que se pueden asignar masivamente
    protected $fillable = [
        'numero_viaje',
        'fk_ruta',
        'fk_licencia_requerida',
        'fk_certificacion_requerida', // Mantén esta relación como única
        'fk_operador',
        'fk_unidad',
        'fecha_salida',
        'fecha_llegada',
        'estado',
        'pago_operador',
    ];

    // Definir la clave primaria
    protected $primaryKey = 'id_viaje';

    // Indicar si no usamos timestamps
    public $timestamps = false;

    // Relaciones con otras tablas
    public function ruta()
    {
        return $this->belongsTo(Ruta::class, 'fk_ruta');
    }

    public function licencia()
    {
        return $this->belongsTo(Licencia::class, 'fk_licencia_requerida');
    }

    // Cambiar la relación de muchos a muchos a uno a uno
    public function certificacion()
    {
        return $this->belongsTo(Certificacion::class, 'fk_certificacion_requerida'); // Ahora es 'belongsTo' en vez de 'belongsToMany'
    }

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'fk_operador');
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'fk_unidad');
    }
}