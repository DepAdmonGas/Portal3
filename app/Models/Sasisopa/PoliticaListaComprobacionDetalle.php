<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class PoliticaListaComprobacionDetalle extends Model
{
    protected $table = 'tb_politica_lista_comprobacion_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_lista_comprobacion',
        'criterio',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista_comprobacion' => 'integer',
    ];


}
