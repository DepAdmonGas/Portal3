<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class CursoTema extends Model
{
    protected $table = 'tb_cursos_temas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_modulo',
        'num_tema',
        'titulo',
        'archivo',
        'confi_mes',
        'confi_lista',
        'categoria',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_modulo' => 'integer',
        'num_tema' => 'integer',
        'titulo' => 'string',
        'archivo' => 'string',
        'confi_mes' => 'integer',
        'confi_lista' => 'integer',
        'categoria' => 'string',
        'estado' => 'integer',
    ];
}
