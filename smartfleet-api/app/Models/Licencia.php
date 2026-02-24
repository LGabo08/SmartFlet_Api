<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Licencia extends Model
{
    use HasFactory;

    // Especifica la tabla de la base de datos (si no coincide con el nombre del modelo en plural)
    protected $table = 'licencia';

    // Especifica los campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre_licencia',
        'descripcion_licencia',
    ];
}