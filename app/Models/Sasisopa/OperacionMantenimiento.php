<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class OperacionMantenimiento extends Model
{
    protected $table = 'tb_operacion_mantenimiento';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'norma',
        'nombre',
        'link',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'estado' => 'integer',
    ];
}
