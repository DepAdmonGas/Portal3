<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class InformeRevisionResultado extends Model
{
    protected $table = 'tb_informe_revision_resultados';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'archivo' => 'string',
    ];
}