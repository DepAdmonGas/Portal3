<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FormatoPreciosTransporte extends Model
{
    protected $table = 'op_formato_precios_transporte';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formato',
        'detalle',
        'precio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formato' => 'integer',
        'detalle' => 'string',
        'precio' => 'double',
    ];
}

