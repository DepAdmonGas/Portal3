<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class InvestigacionIncidenteAccidenteNo extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente_no';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'id_usuario',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'estatus' => 'integer',
        'fecha' => 'date',
    ];
}
