<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoriaEntrevistador extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_entrevistador';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'nombre',
        'puesto',
        'area_descripcion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'nombre' => 'string',
        'puesto' => 'string',
        'area_descripcion' => 'string',
    ];
}
