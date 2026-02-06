<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foro extends Model
{
    protected $table = 'tb_foro';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'mensaje',
        'fecha_hora',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];
}
