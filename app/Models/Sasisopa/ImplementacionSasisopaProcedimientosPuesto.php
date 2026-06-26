<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ImplementacionSasisopaProcedimientosPuesto extends Model
{
    protected $table = 'tb_implementacion_sasisopa_procedimientos_puesto';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_lista',
        'puesto',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_lista' => 'integer',
    ];
}
