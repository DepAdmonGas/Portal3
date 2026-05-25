<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetricoResumen extends Model
{
    protected $table = 'op_control_volumetrico_resumen';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'producto',
        'dato1',
        'dato2',
        'dato3',
        'dato4',
        'dato5',
        'dato6',
        'dato7',
        'dato8',
        'dato9',
        'dato10',
        'dato11',
        'dato12',
        'dato13',
        'dato14',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'producto' => 'string',
        'dato1' => 'double',
        'dato2' => 'double',
        'dato3' => 'double',
        'dato4' => 'double',
        'dato5' => 'double',
        'dato6' => 'double',
        'dato7' => 'double',
        'dato8' => 'double',
        'dato9' => 'double',
        'dato10' => 'double',
        'dato11' => 'double',
        'dato12' => 'double',
        'dato13' => 'double',
        'dato14' => 'double',
        'comentario' => 'string',
    ];
}

