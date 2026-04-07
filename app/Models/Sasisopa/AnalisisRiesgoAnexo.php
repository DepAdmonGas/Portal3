<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class AnalisisRiesgoAnexo extends Model
{
    protected $table = 'tb_analisis_riesgo_anexos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_analisis',
        'descripcion',
        'documento',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_analisis' => 'integer',
        'descripcion' => 'string',
        'documento' => 'string',
    ];
}
