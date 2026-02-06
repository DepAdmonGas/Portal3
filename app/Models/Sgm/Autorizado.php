<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Autorizado extends Model
{
    protected $table = 'sgm_autorizado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_hora',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
        'estado' => 'integer',
    ];
}
