<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class AuditoriaInternaAnexo extends Model
{
    protected $table = 'tb_auditoria_interna_anexos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'formato',
        'documento',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'formato' => 'integer',
        'documento' => 'string',
        'archivo' => 'string',
    ];
}
