<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OperadorCuota extends Model
{
    use HasFactory;

    protected $table = 'operador_cuota';
    protected $primaryKey = 'id_op_cuota';

    public $timestamps = false;

    protected $fillable = [
        'fk_operador',
        'periodo',
        'cuota_objetivo',
        'cuota_realizada',
    ];

    protected $casts = [
        'id_op_cuota' => 'integer',
        'fk_operador' => 'integer',
        'cuota_objetivo' => 'integer',
        'cuota_realizada' => 'integer',
    ];

    protected $appends = [
        'cuota_restante',
        'estado_cuota',
    ];

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'fk_operador', 'id_operador');
    }

    public function getCuotaRestanteAttribute(): int
    {
        $objetivo = (int) ($this->cuota_objetivo ?? 0);
        $realizada = (int) ($this->cuota_realizada ?? 0);

        return max($objetivo - $realizada, 0);
    }

    public function getEstadoCuotaAttribute(): string
    {
        $objetivo = (int) ($this->cuota_objetivo ?? 0);
        $restante = $this->cuota_restante;

        if ($objetivo <= 0) {
            return 'SIN_CONFIGURAR';
        }

        if ($restante <= 0) {
            return 'AGOTADA';
        }

        if ($restante <= max(1, (int) floor($objetivo * 0.2))) {
            return 'BAJA';
        }

        return 'ACTIVA';
    }

    public function cuotas()
{
    return $this->hasMany(\App\Models\OperadorCuota::class, 'fk_operador', 'id_operador');
}
}