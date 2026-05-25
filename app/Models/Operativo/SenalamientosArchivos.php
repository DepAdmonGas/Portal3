<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SenalamientosArchivos extends Model
{
    protected $table = 'op_senalamientos_archivos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_imagen',
        'fecha',
        'descripcion',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_imagen' => 'integer',
        'fecha' => 'date'
    ];
}

