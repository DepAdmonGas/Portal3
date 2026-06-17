<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ProtocoloEmergenciasAnexo extends Model
{
    protected $table = 'tb_protocolo_emergencias_anexo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fechacreacion',
        'nombre_anexo',
        'id_protocolo',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_protocolo' => 'integer',
        'fechacreacion' => 'datetime',
    ];

}
