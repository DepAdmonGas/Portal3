<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhOrganigramaEstacion extends Model
{
    protected $table = 'op_rh_organigrama_estacion';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'version',
        'archivo',
        'observaciones'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'version' => 'integer',
        'fechacreacion' => 'datetime'
    ];
}

