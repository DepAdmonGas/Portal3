<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoServicio extends Model
{
    protected $table = 'op_pago_servicios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha_hora',
        'concepto',
        'recibo',
        'pago'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha_hora' => 'datetime',
        'concepto' => 'string',
        'recibo' => 'string',
        'pago' => 'string'
    ];
}
