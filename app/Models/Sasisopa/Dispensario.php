<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class Dispensario extends Model
{
    protected $table = 'tb_dispensarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_dispensario',
        'marca',
        'modelo',
        'serie',
        'producto1',
        'producto2',
        'producto3',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'no_dispensario' => 'integer',
        'marca' => 'string',
        'modelo' => 'string',
        'serie' => 'string',
        'producto1' => 'string',
        'producto2' => 'string',
        'producto3' => 'string',
        'estado' => 'integer',
    ];
}
