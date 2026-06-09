<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sasisopa\SondasMedicion;

class CalibracionEquipoSonda extends Model
{
    protected $table = 'tb_calibracion_equipos_sonda';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'id_sonda',
        'resultado1',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_sonda' => 'integer',
        'resultado1' => 'string',
    ];

    public function sonda()
    {
        return $this->belongsTo(
            SondasMedicion::class,
            'id_sonda',
            'id'
        );
    }
}
