<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TanqueAlmacenamiento extends Model
{
    protected $table = 'tb_tanque_almacenamiento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_tanque',
        'capacidad',
        'producto'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int'
    ];
}
