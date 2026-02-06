<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaNegra extends Model
{
    protected $table = 'tb_lista_negra';

    protected $primaryKey = 'id_lista';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'fenacimiento',
        'feingreso',
        'febaja',
        'estacion',
        'motivo',
        'foto',
    ];

    protected $casts = [
        'id_lista' => 'integer',
        'fenacimiento' => 'date',
        'feingreso' => 'date',
        'febaja' => 'date',
    ];
}
