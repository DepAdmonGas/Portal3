<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraFirma extends Model
{
    protected $table = 'op_orden_compra_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_ordencompra' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'tipo_firma' => 'string',
        'firma' => 'string'
    ];

}

