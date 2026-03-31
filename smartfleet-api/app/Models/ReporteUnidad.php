<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteUnidad extends Model
{
    use HasFactory;

    protected $table = 'reporte_unidad';  // Nombre de la tabla
    protected $primaryKey = 'id_reporte';  // Clave primaria

    // Definir los campos que se pueden llenar
    protected $fillable = [
        'fk_unidad',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'fecha_reporte',
    ];

    // Si no usamos timestamps
    public $timestamps = false;
}