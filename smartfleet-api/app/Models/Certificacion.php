<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificacion extends Model
{
    use HasFactory;

    protected $table = 'certificacion';
    protected $primaryKey = 'id_certificacion';
    public $timestamps = false;

    // Se agrega fk_cliente al array de fillable
    protected $fillable = [
        'nombre_certificacion',
        'descripcion',
        'cliente',  // Esto es el nombre del cliente, si necesitas la FK
        'fk_cliente', // Añadimos la foreign key fk_cliente
    ];

    /**
     * Relación con el modelo de Cliente.
     * Cada certificación pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente');  // Aquí asociamos fk_cliente con la tabla Cliente
    }
}