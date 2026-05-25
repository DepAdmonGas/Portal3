<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class NivelExplosividadFirma extends Model
{
    protected $table = 'op_nivel_explosividad_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'tipo_firma',
        'imagen_firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'tipo_firma' => 'string',
        'imagen_firma' => 'string',
    ];
}

