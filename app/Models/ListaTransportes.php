<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaTransportes extends Model
{
    protected $table = 'tb_lista_transportes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_transporte',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'estado' => 'integer',
    ];
}
