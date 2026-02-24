<?php

// app/Models/Ruta.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruta extends Model
{
    use HasFactory;

    // Especifica la tabla de la base de datos (si no coincide con el nombre del modelo en plural)
    protected $table = 'ruta';

    // Especifica los campos que se pueden asignar masivamente
    protected $fillable = [
        'fk_zona_origen',
        'fk_zona_destino',
        'distancia_km',
        'tarifa_operador',
    ];

    // Puedes agregar las relaciones de zona, si es necesario
    public function zonaOrigen()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_origen');
    }

    public function zonaDestino()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_destino');
    }
}
