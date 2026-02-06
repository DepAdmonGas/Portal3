<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganigramaEstaciones extends Model
{
    protected $table = 'tb_organigrama_estaciones';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'registro_patronal',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'estado',
        'municipio',
        'numero_telefono',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'codigo_postal' => 'integer',
        'numero_telefono' => 'integer',
    ];
}
