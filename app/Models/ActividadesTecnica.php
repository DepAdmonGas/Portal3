<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadesTecnica extends Model
{
    protected $table = 'tb_actividades_tecnicas';

    protected $primaryKey = 'id_actividades_tecnicas';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_a',
        'archivo',
    ];

    protected $casts = [
        'id_actividades_tecnicas' => 'integer',
        'nombre_a' => 'string',
        'archivo' => 'string',
    ];
}
