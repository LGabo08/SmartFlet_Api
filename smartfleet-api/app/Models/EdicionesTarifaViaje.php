<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdicionesTarifaViaje extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'ediciones_tarifa_viaje';

    // Columnas que son asignables
    protected $fillable = [
        'fk_viaje', 
        'campo_editado', 
        'valor_anterior', 
        'valor_nuevo', 
        'motivo_edicion'
    ];

    // Relación con el modelo Viaje
    public function viaje()
    {
        return $this->belongsTo(Viaje::class, 'fk_viaje');
    }
}