<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganigramaPlantilla extends Model
{
    protected $table = 'tb_organigrama_plantilla';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'nombre',
        'descripcion',
        'documento_perfil',
        'documento_contrato',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'status' => 'integer',
    ];
}
