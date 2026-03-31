<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViajeRechazado extends Model
{
    use HasFactory;

    // Definir la tabla correspondiente
    protected $table = 'viaje_rechazado';

    // Definir los campos que se pueden asignar masivamente
    protected $fillable = [
        'fk_viaje',       // ID del viaje
        'motivos',         // Motivo de la cancelación
        'fecha_rechazo',   // Fecha de la cancelación
    ];

    // Establecer las relaciones de claves foráneas
    public function viaje()
    {
        // Un viaje rechazado pertenece a un viaje
        return $this->belongsTo(Viaje::class, 'fk_viaje', 'id_viaje');
    }
    
}