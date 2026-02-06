<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionResultados extends Model
{
    protected $table = 'tb_revision_resultados';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'archivo'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha_hora' => 'datetime'
    ];
}
