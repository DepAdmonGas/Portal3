<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenTarjeta extends Model
{
    protected $table = 'tb_imagen_tarjeta';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'ruta',
        'no_solicitud',
        'estacion',
    ];

    protected $casts = [
        'id' => 'integer',
        'no_solicitud' => 'integer',
    ];
}
