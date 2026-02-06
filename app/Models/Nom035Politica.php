<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nom035Politica extends Model
{
    protected $table = 'tb_nom_035_politica';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'politica',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
    ];
}
