<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalListaNegra extends Model
{
    protected $table = 'op_rh_personal_lista_negra';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'fecha',
        'motivo',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_personal' => 'integer',
        'fecha' => 'date',
    ];

}
