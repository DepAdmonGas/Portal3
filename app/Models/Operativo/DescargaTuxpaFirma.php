<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescargaTuxpaFirma extends Model
{
    protected $table = 'op_descarga_tuxpa_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_descarga',
        'tipo_firma',
        'imagen_firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_descarga' => 'integer',
        'tipo_firma' => 'string',
        'imagen_firma' => 'string',
    ];
}
