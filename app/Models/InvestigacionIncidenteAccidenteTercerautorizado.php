<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestigacionIncidenteAccidenteTercerautorizado extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente_tercerautorizado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_investigacion',
        'nombre',
        'numero',
        'lider',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_investigacion' => 'integer',
        'fecha' => 'date',
    ];
}
