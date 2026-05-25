<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MiselaneaDocumento extends Model
{
    protected $table = 'op_miselanea_documentos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_lista',
        'documento',
        'categoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista' => 'string',
        'documento' => 'string',
        'categoria' => 'integer',
    ];
}

