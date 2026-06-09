<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sasisopa\Dispensario;

class CalibracionEquipoDispensario extends Model
{
    protected $table = 'tb_calibracion_equipos_dispensario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'id_dispensario',
        'resultado1',
        'resultado2',
        'resultado3',
        'resultado4',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_dispensario' => 'integer',
        'resultado1' => 'string',
        'resultado2' => 'string',
        'resultado3' => 'string',
        'resultado4' => 'string',
    ];

     public function dispensario()
    {
        return $this->belongsTo(
            Dispensario::class,
            'id_dispensario',
            'id'
        );
    }
}
