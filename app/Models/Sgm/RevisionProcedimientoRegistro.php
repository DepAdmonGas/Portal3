<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class RevisionProcedimientoRegistro extends Model
{
    protected $table = 'sgm_revision_procedimiento_registro';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'lugar',
        'elemento',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'lugar' => 'string',
        'elemento' => 'integer',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];
}
