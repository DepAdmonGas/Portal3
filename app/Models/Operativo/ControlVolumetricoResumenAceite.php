<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetricoResumenAceite extends Model
{
    protected $table = 'op_control_volumetrico_resumen_aceites';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'piezas',
        'volumetrico',
        'contables',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'piezas' => 'integer',
        'volumetrico' => 'double',
        'contables' => 'double',
    ];
}

