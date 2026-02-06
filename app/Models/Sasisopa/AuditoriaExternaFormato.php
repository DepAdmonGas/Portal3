<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaExternaFormato extends Model
{
    protected $table = 'tb_auditoria_externa_formato';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'fechacreacion',
        'formato',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'fechacreacion' => 'datetime',
        'formato' => 'string',
        'archivo' => 'string',
    ];
}
