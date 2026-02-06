<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalDocumentos extends Model
{
    protected $table = 'op_rh_personal_documentos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'fechacreacion',
        'detalle',
        'documento'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_personal' => 'integer',
        'fechacreacion' => 'datetime'
    ];

}
