<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PapeleriaLista extends Model
{
    protected $table = 'tb_papeleria_lista';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'producto',
        'costo',
        'inventario',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'inventario' => 'integer',
        'estatus' => 'integer',
    ];
}
