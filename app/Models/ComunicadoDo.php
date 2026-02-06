<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicadoDo extends Model
{
    protected $table = 'tb_comunicados_do';

    protected $primaryKey = 'id_comunicado';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id_comunicado' => 'integer',
        'titulo' => 'string',
        'fecha' => 'date',
        'archivo' => 'string',
    ];
}
