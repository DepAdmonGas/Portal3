<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimpiezaLista extends Model
{
    protected $table = 'tb_limpieza_lista';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'unidad',
        'producto',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
