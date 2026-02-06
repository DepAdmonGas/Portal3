<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetricoPrefijo extends Model
{
    protected $table = 'op_control_volumetrico_prefijos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'serie',
        'descripcion',
        'total',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'serie' => 'string',
        'descripcion' => 'string',
        'total' => 'double',
    ];
}
