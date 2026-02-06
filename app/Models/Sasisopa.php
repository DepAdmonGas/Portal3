<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sasisopa extends Model
{
    protected $table = 'tb_sasisopa';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'version',
        'documento'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int'
    ];
}
