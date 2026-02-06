<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentasGas extends Model
{
    protected $table = 'tb_ventasgas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nom_usuario',
        'magna',
        'premium',
        'diesel',
        'fecha',
        'estatus'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'estatus' => 'int',
        'fecha' => 'date'
    ];
}
