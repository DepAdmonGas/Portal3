<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class InventarioEquipo extends Model
{
    protected $table = 'sgm_inventario_equipo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nombre',
        'identificacion',
        'funcion',
        'fecha_instalacion',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'nombre' => 'string',
        'identificacion' => 'string',
        'funcion' => 'string',
        'fecha_instalacion' => 'date',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];
}
