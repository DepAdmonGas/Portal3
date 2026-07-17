<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class SeguimientoObjetivoIndicador extends Model
{
    protected $table = 'sgm_seguimiento_objetivo_indicador';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'lugar',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'lugar' => 'string',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];

    public function implementacion()
    {
        return $this->hasOne(
            SeguimientoImplementacionSgm::class,
            'id_seguimiento'
        );
    }

    public function calibracion()
    {
        return $this->hasOne(
            SeguimientoCalibracionEquipo::class,
            'id_seguimiento'
        );
    }

    public function satisfaccion()
    {
        return $this->hasOne(
            SeguimientoSatisfaccionCliente::class,
            'id_seguimiento'
        );
    }

    public function asistentes()
    {
        return $this->hasMany(
            SeguimientoAsistente::class,
            'id_seguimiento'
        );
    }
    
}
