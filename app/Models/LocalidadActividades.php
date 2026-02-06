<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalidadActividades extends Model
{
    protected $table = 'tb_localidad_actividades';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_localidad',
        'detalle',
        'costo_derechos',
        'costo_aportaciones',
        'tiempo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_localidad' => 'integer',
        'costo_derechos' => 'double',
        'costo_aportaciones' => 'double',
        'tiempo' => 'integer',
    ];
}
