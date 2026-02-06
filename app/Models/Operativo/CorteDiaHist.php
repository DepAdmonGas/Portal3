<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteDiaHist extends Model
{
    protected $table = 'op_corte_dia_hist';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_corte',
        'id_usuario',
        'fecha',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_corte' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'detalle' => 'string',
    ];
}
