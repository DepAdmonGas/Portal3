<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class InvestigacionIncidenteAccidente extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'id_usuario',
        'descripcion',
        'tipo_evento',
        'muertes',
        'tercer_autorizado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'tipo_evento' => 'integer',
        'muertes' => 'integer',
        'tercer_autorizado' => 'integer',
        'fechacreacion' => 'datetime',
    ];

    public function formatos()
    {
        return $this->hasMany(
            InvestigacionIncidenteAccidenteFormato::class,
            'id_investigacion'
        );
    }

    public function grupos()
    {
        return $this->hasMany(
            InvestigacionIncidenteAccidenteGrupo::class,
            'id_investigacion'
        );
    }
}
