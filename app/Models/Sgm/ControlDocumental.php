<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlDocumental extends Model
{
    protected $table = 'sgm_control_documental';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_documento',
        'id_estacion',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_documento' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'archivo' => 'string',
    ];
}
