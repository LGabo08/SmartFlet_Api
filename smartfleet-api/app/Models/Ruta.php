<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruta extends Model
{
    use HasFactory;

    // Especifica la tabla de la base de datos
    protected $table = 'ruta';

    // Especifica los campos que se pueden asignar masivamente
    protected $fillable = [
        'fk_zona_origen',
        'fk_zona_destino',
        'distancia_km',
        'tarifa_operador',
        'nombre_ruta', // Agregado el nombre de la ruta
    ];



    public function viajes()
{
    return $this->hasMany(Viaje::class, 'fk_ruta');  // Relación inversa
}
    // Relaciones con las zonas
    public function zonaOrigen()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_origen');
    }

    public function zonaDestino()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_destino');
    }
}