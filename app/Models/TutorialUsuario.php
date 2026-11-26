<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialUsuario extends Model
{
    protected $table = 'tb_tutorial_usuario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'completado_en',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'completado_en' => 'datetime',
    ];
}
