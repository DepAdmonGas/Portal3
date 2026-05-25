<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CorteDiaFirmas extends Model
{
    protected $table = 'op_corte_dia_firmas';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reportedia',
        'id_usuario',
        'fecha',
        'firma',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reportedia' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'firma' => 'string',
        'detalle' => 'string',
    ];
}

