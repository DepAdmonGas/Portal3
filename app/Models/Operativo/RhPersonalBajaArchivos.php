<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalBajaArchivos extends Model
{
    protected $table = 'op_rh_personal_baja_archivos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_baja',
        'descripcion',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_baja' => 'integer',
    ];


}

