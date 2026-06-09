<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class JarraPatron extends Model
{
    protected $table = 'tb_jarra_patron';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'marca',
        'no_serie',
        'capacidad',
        'material',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'estado' => 'integer',
    ];
}
