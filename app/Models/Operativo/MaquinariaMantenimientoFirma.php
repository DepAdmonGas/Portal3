<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoFirma extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_ocurrencia',
        'id_usuario',
        'tipo_firma',
        'firma',
        'fecha',
        'token_usado',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'id_ocurrencia'    => 'integer',
        'id_usuario'       => 'integer',
    ];
}