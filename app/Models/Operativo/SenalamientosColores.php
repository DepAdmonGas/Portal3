<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SenalamientosColores extends Model
{
    protected $table = 'op_senalamientos_colores';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_senalamiento',
        'titulo',
        'detalle'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_senalamiento' => 'integer'
    ];
}

