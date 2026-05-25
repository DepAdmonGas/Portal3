<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class DirectorioFin extends Model
{
    protected $table = 'op_directorio_fin';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
    ];
}

