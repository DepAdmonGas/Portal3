<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAuditorias extends Model
{
    protected $table = 'tb_programa_auditorias';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'tipo_auditoria',
        'responsable',
        'periodicidad',
        'fecha',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}
