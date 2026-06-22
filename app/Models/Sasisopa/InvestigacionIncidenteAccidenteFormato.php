<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class InvestigacionIncidenteAccidenteFormato extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente_formato';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_investigacion',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_investigacion' => 'integer',
        'fechacreacion' => 'datetime'
    ];

    public function investigacion()
    {
        return $this->belongsTo(
            InvestigacionIncidenteAccidente::class,
            'id_investigacion'
        );
    }
}