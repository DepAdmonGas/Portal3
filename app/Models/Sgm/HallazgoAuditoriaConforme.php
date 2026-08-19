<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoriaConforme extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_conformes';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'descripcion',
        'evidencia',
        'criterio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
    ];
}
