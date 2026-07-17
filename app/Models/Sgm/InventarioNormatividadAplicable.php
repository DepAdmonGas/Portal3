<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class InventarioNormatividadAplicable extends Model
{
    protected $table = 'sgm_inventario_normatividad_aplicable';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'norma',
        'fecha_publicacion',
        'fecha_aplicacion',
        'equipo',
        'link',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'norma' => 'string',
        'fecha_publicacion' => 'date',
        'fecha_aplicacion' => 'date',
        'equipo' => 'string',
        'link' => 'string',
        'estado' => 'integer',
    ];
}
