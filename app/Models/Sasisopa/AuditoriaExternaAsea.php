<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaExternaAsea extends Model
{
    protected $table = 'tb_auditoria_externa_asea';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'fechacreacion',
        'archivo',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'fechacreacion' => 'datetime',
        'archivo' => 'string',
        'comentario' => 'string',
    ];
}
