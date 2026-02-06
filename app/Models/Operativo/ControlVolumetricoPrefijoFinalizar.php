<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetricoPrefijoFinalizar extends Model
{
    protected $table = 'op_control_volumetrico_prefijos_finalizar';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'estado' => 'integer',
    ];
}
