<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientesControlgas extends Model
{
    protected $table = 'op_clientes_controlgas';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No hay created_at ni updated_at

    protected $fillable = [
        'idreporte_dia',
        'concepto',
        'pago',
        'consumo'
    ];

    protected $casts = [
        'idreporte_dia' => 'integer',
        'pago'          => 'double',
        'consumo'       => 'double',
    ];

}
