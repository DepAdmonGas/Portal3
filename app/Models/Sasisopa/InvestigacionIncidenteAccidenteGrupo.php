<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class InvestigacionIncidenteAccidenteGrupo extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente_grupo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_investigacion',
        'fechacreacion',
        'nombre',
        'puesto',
        'especialidad',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_investigacion' => 'integer',
        'fechacreacion' => 'datetime',
    ];
}
