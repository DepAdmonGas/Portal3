<?php

namespace App\Models\Sasisopa;

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
        'producto',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int'
    ];
}
