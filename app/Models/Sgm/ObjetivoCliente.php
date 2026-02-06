<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObjetivoCliente extends Model
{
    protected $table = 'sgm_objetivos_cliente';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'id_estacion',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'id_estacion' => 'integer',
        'detalle' => 'string',
    ];
}
