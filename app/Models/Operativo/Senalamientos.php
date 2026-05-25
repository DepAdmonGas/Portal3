<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Senalamientos extends Model
{
    protected $table = 'op_senalamientos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_imagen',
        'imagen',
        'dimension',
        'ubicacion',
        'reproduccion',
        'vinil',
        'placa',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_imagen' => 'integer',
        'vinil' => 'integer',
        'placa' => 'integer',
        'status' => 'integer'
    ];
}

