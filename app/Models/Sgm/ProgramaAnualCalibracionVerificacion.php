<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualCalibracionVerificacion extends Model
{
    protected $table = 'sgm_programa_anual_calibracion_verificacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_equipo',
        'fecha',
        'id_verificar',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_equipo' => 'integer',
        'fecha' => 'date',
        'id_verificar' => 'integer',
        'estado' => 'integer',
    ];

    public function equipo()
    {
        return $this->belongsTo(
            PatronInstrumento::class,
            'id_equipo'
        );
    }

    public function verificar()
    {
        return $this->belongsTo(
            InventarioEquipo::class,
            'id_verificar'
        );
    }
}
