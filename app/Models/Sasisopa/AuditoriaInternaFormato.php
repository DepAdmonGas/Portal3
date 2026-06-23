<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class AuditoriaInternaFormato extends Model
{
    protected $table = 'tb_auditoria_interna_formato';

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
