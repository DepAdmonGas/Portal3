<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicacionEvidencia extends Model
{
    protected $table = 'se_comunicacion_evidencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_comunicacion',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_comunicacion' => 'integer',
        'archivo' => 'string',
    ];
}
