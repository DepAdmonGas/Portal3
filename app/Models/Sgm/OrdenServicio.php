<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenServicio extends Model
{
    protected $table = 'sgm_orden_servicio';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'hora',
        'id_solicitante',
        'descripcion',
        'justificacion',
        'realizadopor',
        'folio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'id_solicitante' => 'integer',
        'descripcion' => 'string',
        'justificacion' => 'string',
        'realizadopor' => 'integer',
        'folio' => 'integer',
    ];
}
