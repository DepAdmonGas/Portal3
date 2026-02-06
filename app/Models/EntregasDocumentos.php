<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntregasDocumentos extends Model
{
    protected $table = 'tb_entregas_documentos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_entrega',
        'id_estacion',
        'documento',
        'fecha',
        'detalle',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_entrega' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}
