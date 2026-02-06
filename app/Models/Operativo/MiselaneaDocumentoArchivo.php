<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiselaneaDocumentoArchivo extends Model
{
    protected $table = 'op_miselanea_documentos_archivo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'id_documento',
        'fecha',
        'detalle',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
        'id_documento' => 'integer',
        'fecha' => 'datetime',
        'detalle' => 'string',
        'archivo' => 'string',
    ];
}
