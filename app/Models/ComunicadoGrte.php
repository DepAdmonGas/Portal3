<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicadoGrte extends Model
{
    protected $table = 'tb_comunicados_grte';

    protected $primaryKey = 'id_comunicado_grte';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_comunicado',
        'id_gerente',
        'fecha',
    ];

    protected $casts = [
        'id_comunicado_grte' => 'integer',
        'id_comunicado' => 'integer',
        'id_gerente' => 'integer',
        'fecha' => 'datetime',
    ];
}
