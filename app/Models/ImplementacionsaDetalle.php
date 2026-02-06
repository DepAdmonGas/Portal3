<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImplementacionsaDetalle extends Model
{
    protected $table = 'tb_implementacionsa_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_implementacion',
        'pregunta',
        'respuesta',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_implementacion' => 'integer',
        'resultado' => 'integer',
    ];

}
