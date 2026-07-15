<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class Politica extends Model
{
    protected $table = 'sgm_politica';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'contenido',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'contenido' => 'string',
    ];
}
