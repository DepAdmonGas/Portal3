<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FacturaTelcel extends Model
{
    protected $table = 'op_factura_telcel';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha_hora',
        'detalle',
        'factura',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha_hora' => 'datetime',
        'detalle' => 'string',
        'factura' => 'string',
    ];
}

