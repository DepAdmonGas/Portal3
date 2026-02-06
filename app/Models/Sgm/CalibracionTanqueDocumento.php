<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibracionTanqueDocumento extends Model
{
    protected $table = 'tb_calibracion_tanques_documentos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'nombre' => 'string',
    ];
}
