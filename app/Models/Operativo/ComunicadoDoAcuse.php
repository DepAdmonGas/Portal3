<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Acuse de "De Acuerdo" de un comunicado (tabla heredada).
 *
 * Una fila por usuario que confirmó el comunicado. id_gerente conserva el
 * nombre de la columna original, pero guarda el id del usuario (tb_usuarios).
 */
class ComunicadoDoAcuse extends Model
{
    protected $table = 'tb_comunicados_grte';

    protected $primaryKey = 'id_comunicado_grte';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_comunicado',
        'id_gerente',
    ];

    protected $casts = [
        'id_comunicado_grte' => 'integer',
        'id_comunicado' => 'integer',
        'id_gerente' => 'integer',
        'fecha' => 'datetime',
    ];
}
