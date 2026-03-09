<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla si no sigue la convención
    protected $table = 'cliente';

    // Definir la clave primaria si no es "id"
    protected $primaryKey = 'id_cliente';

    // Deshabilitar el uso de timestamps automáticos si no son necesarios
    public $timestamps = true;

    // Los campos que pueden ser llenados de manera masiva
    protected $fillable = [
        'nombre_cliente',
        'rfc',
        'activo'
    ];

    // Relación: Un cliente tiene muchas certificaciones
    public function certificaciones()
    {
        return $this->hasMany(Certificacion::class, 'fk_cliente');
    }
}