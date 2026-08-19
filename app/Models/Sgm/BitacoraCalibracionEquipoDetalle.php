<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class BitacoraCalibracionEquipoDetalle extends Model
{
    protected $table = 'sgm_bitacora_calibracion_equipo_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'id_equipo',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'id_equipo' => 'integer',
        'resultado' => 'string',
    ];

    public function equipo()
    {
        return $this->belongsTo(
            InventarioEquipo::class,
            'id_equipo'
        );
    }

    public function bitacora()
    {
        return $this->belongsTo(
            BitacoraCalibracionEquipo::class,
            'id_programa',
            'id_programa'
        );
    }
}
