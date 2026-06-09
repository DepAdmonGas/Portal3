<?php

namespace App\Models\Sgm;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sasisopa\JarraPatron;

class CalibracionEquipoJarra extends Model
{
    protected $table = 'tb_calibracion_equipos_jarra';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'id_jarra',
        'resultado1',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_jarra' => 'integer',
        'resultado1' => 'string',
    ];

    public function jarra()
    {
        return $this->belongsTo(
            JarraPatron::class,
            'id_jarra'
        );
    }
}
