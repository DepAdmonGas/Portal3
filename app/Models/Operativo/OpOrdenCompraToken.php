<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpOrdenCompraToken extends Model
{
    protected $table = 'op_orden_compra_token';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_usuario',
        'token'
    ];
}