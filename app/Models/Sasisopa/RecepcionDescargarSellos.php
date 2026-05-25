<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RecepcionDescargarSellos extends Model
{
    protected $table = 'tb_recepcion_descargar_sellos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_recepcion_descarga',
        'verificar',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_recepcion_descarga' => 'integer',
    ];
}
