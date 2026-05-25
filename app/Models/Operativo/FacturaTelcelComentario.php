<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FacturaTelcelComentario extends Model
{
    protected $table = 'op_factura_telcel_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'comentario' => 'string',
    ];
}

