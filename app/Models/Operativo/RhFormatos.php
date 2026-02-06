<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhFormatos extends Model
{
    protected $table = 'op_rh_formatos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_localidad',
        'formato',
        'fecha',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_localidad' => 'integer',
        'formato' => 'integer',
        'status' => 'integer',
        'fecha' => 'datetime'
    ];
}
