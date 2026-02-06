<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinturasLista extends Model
{
    protected $table = 'tb_pinturas_lista';

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
