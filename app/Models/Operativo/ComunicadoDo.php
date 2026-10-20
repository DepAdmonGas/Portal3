<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Comunicado global del Departamento Operativo (tabla heredada).
 *
 * Los comunicados no pertenecen a una estación: son globales para todo el
 * personal con acceso al módulo del Home. El acuse de cada usuario vive en
 * tb_comunicados_grte (ver ComunicadoDoAcuse).
 */
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
        'fecha' => 'date',
    ];
}
