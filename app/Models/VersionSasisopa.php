<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersionSasisopa extends Model
{
    protected $table = 'tb_version_sasisopa';

    protected $primaryKey = 'id_version_sasisopa';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'documentos',
        'version'
    ];

    protected $casts = [
        'id_version_sasisopa' => 'int',
        'id_estacion' => 'int'
    ];
}
