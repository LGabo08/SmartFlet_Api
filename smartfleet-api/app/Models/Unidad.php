<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\UsuarioScope;
class Unidad extends Model
{
    use HasFactory;

    // Definir la tabla de la base de datos
    protected $table = 'unidad';
     public $timestamps = false;  // Esto desactiva la gestión automática de las columnas created_at y updated_at
    // Definir la clave primaria
    protected $primaryKey = 'id_unidad';

    // Indicar que la columna 'id_unidad' no es autoincremental
    public $incrementing = false;

    // Definir los campos que se pueden asignar masivamente
    protected $fillable = [
         'fk_usuario', 
        'numero_economico',
        'fk_zona_actual',
        'estado',
        'fk_licencia_requerida',
    ];
     
    protected static function booted(): void
{
    static::addGlobalScope(new UsuarioScope());

    static::creating(function (self $model) {
        if (auth('api')->check() && empty($model->fk_usuario)) {
            $model->fk_usuario = auth('api')->id();
        }
    });
}
    // Relaciones con otras tablas
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'fk_zona_actual', 'id_zona');
    }

    public function licencia()
    {
        return $this->belongsTo(Licencia::class, 'fk_licencia_requerida', 'id_licencia');
    }

    // Relación con la tabla viajes (si aplica)
    public function viajes()
    {
        return $this->hasMany(Viaje::class, 'fk_unidad');
    }
}