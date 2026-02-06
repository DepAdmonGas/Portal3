<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtocoloEmergencias extends Model
{
    protected $table = 'tb_protocolo_emergencias';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fechacreacion' => 'date',
    ];
}
