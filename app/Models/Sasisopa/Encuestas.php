<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class Encuestas extends Model
{
    protected $table = 'tb_encuestas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fechacreacion',
        'nombre',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'fechacreacion' => 'datetime',
        'estado' => 'integer',
    ];
}
