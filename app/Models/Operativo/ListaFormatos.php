<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaFormatos extends Model
{
    protected $table = 'op_lista_formatos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'formato',
        'nombre',
        'archivo',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'formato' => 'string',
        'nombre' => 'string',
        'archivo' => 'string',
        'status' => 'integer',
    ];
}
