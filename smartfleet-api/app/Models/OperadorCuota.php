<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class OperadorCuota extends Model
{
    use HasFactory;

    protected $table      = 'operador_cuota';
    protected $primaryKey = 'id_op_cuota';

    public $timestamps = false;

    protected $fillable = [
        'fk_operador',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'cuota_objetivo',
        'cuota_realizada',
    ];

    protected $casts = [
        'id_op_cuota'    => 'integer',
        'fk_operador'    => 'integer',
        'cuota_objetivo' => 'integer',
        'cuota_realizada'=> 'integer',
        'fecha_inicio'   => 'date:Y-m-d',
        'fecha_fin'      => 'date:Y-m-d',
    ];

    protected $appends = [
        'cuota_restante',
        'estado_cuota',
    ];

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'fk_operador', 'id_operador');
    }

    // ── Cuota restante ────────────────────────────────────────────────────
    public function getCuotaRestanteAttribute(): int
    {
        $objetivo  = (int)($this->cuota_objetivo  ?? 0);
        $realizada = (int)($this->cuota_realizada ?? 0);

        return max($objetivo - $realizada, 0);
    }

    // ── Estado de la cuota ────────────────────────────────────────────────
    //
    // Lógica por prioridad:
    //
    //  SIN_CONFIGURAR  → no tiene cuota objetivo definida
    //  AGOTADA         → el periodo ya venció (fecha_fin < hoy)
    //                    independientemente de si cumplió o no
    //  EXCEDIDA        → cuota_realizada > cuota_objetivo (dentro del periodo)
    //  CUMPLIDA        → cuota_realizada >= cuota_objetivo (dentro del periodo)
    //  ACTIVA          → tiene movimientos y le falta por cumplir
    //  PENDIENTE       → sin movimientos aún, dentro del periodo
    //
    public function getEstadoCuotaAttribute(): string
    {
        $objetivo  = (int)($this->cuota_objetivo  ?? 0);
        $realizada = (int)($this->cuota_realizada ?? 0);

        // 1. Sin objetivo configurado
        if ($objetivo <= 0) {
            return 'SIN_CONFIGURAR';
        }

        $hoy = now()->toDateString();

        // Necesitamos las fechas como strings para comparar
        // El cast 'date:Y-m-d' devuelve un Carbon, usamos format()
        $fechaFin   = $this->fecha_fin   ? $this->fecha_fin->format('Y-m-d')   : null;
        $fechaInicio = $this->fecha_inicio ? $this->fecha_inicio->format('Y-m-d') : null;

        // 2. Periodo vencido (fecha_fin < hoy) → AGOTADA
        if ($fechaFin && $fechaFin < $hoy) {
            return 'AGOTADA';
        }

        // A partir de aquí estamos dentro del periodo (o sin fecha definida)

        // 3. Excedida (realizó más de lo que se pidió)
        if ($realizada > $objetivo) {
            return 'EXCEDIDA';
        }

        // 4. Cumplida exactamente
        if ($realizada === $objetivo) {
            return 'CUMPLIDA';
        }

        // 5. Activa: tiene movimientos pero aún le falta
        if ($realizada > 0) {
            return 'ACTIVA';
        }

        // 6. Pendiente: sin movimientos todavía
        return 'PENDIENTE';
    }

    public function cuotas()
    {
        return $this->hasMany(\App\Models\OperadorCuota::class, 'fk_operador', 'id_operador');
    }
}