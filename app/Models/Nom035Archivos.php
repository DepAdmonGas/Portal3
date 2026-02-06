<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nom035Archivos extends Model
{
    protected $table = 'tb_nom_035_archivos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'categoria',
        'nom_archivo',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
    ];
}
