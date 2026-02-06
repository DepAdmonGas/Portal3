<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraVerificacionLista extends Model
{
    protected $table = 'sgm_bitacora_verificacion_lista';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'pregunta',
        'categoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'pregunta' => 'string',
        'categoria' => 'string',
    ];
}
