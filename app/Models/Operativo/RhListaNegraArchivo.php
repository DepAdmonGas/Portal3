<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhListaNegraArchivo extends Model
{
    protected $table = 'op_rh_personal_lista_negra_archivos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_lista_negra',
        'descripcion',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista_negra' => 'integer',
    ];
}