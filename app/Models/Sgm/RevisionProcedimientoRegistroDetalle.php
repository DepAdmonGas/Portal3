<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class RevisionProcedimientoRegistroDetalle extends Model
{
    protected $table = 'sgm_revision_procedimiento_registro_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_revision',
        'categoria',
        'pregunta',
        'respuesta',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_revision' => 'integer',
        'categoria' => 'string',
        'pregunta' => 'string',
        'respuesta' => 'string',
    ];
}
