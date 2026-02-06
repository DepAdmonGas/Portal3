<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioCombustible extends Model
{
    protected $table = 'op_precio_combustible';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'dato1',
        'dato2',
        'dato3',
        'dato4',
        'dato5',
        'dato6',
        'dato7',
        'dato8',
        'dato9'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'dato1' => 'double',
        'dato2' => 'double',
        'dato3' => 'double',
        'dato4' => 'double',
        'dato5' => 'double',
        'dato6' => 'double',
        'dato7' => 'double',
        'dato8' => 'double',
        'dato9' => 'double'
    ];
}
