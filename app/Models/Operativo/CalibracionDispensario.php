<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CalibracionDispensario extends Model
{
    protected $table = 'op_calibracion_dispensario';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No created_at ni updated_at

    protected $fillable = [
        'id_estacion',
        'fecha',
        'year',
        'periodo',
        'archivo'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'year'        => 'integer',
        'fecha'       => 'datetime',
    ];

}

