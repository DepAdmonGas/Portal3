<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetrico extends Model
{
    // Nombre de la tabla
    protected $table = 'op_control_volumetrico';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // Timestamps (created_at, updated_at)
    public $timestamps = false; 

    protected $fillable = [
        'id_mes',
        'fecha_hora',
        'anexos',
        'documento',
    ];

    
    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha_hora' => 'date',
        'anexos' => 'string',
        'documento' => 'string',
    ];
}
