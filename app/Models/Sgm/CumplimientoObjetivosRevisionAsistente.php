<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CumplimientoObjetivosRevisionAsistente extends Model
{
    protected $table = 'sgm_cumplimiento_objetivos_revision_asistentes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_cumplimiento',
        'id_usuario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_cumplimiento' => 'integer',
        'id_usuario' => 'integer',
    ];
}
