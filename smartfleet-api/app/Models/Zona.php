<?php

// app/Models/Zona.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    // Definir la tabla
    protected $table = 'zona';

    // Definir la clave primaria
    protected $primaryKey = 'id_zona';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre_zona',
    ];

    // Relación con el modelo 'Operador'
    public function operadores()
    {
        return $this->hasMany(Operador::class, 'fk_zona_actual');
    }

    // Relación con el modelo 'Ruta'
    public function rutas()
    {
        return $this->hasMany(Ruta::class, 'fk_zona_origen');
    }

    // Relación con la tabla 'ZonaVecina' (zona vecina)
    public function zonasVecinas()
    {
        return $this->hasMany(ZonaVecina::class, 'fk_zona');
    }
}