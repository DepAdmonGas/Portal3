<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ImplementacionSasisopaProcedimientos extends Model
{
    protected $table = 'tb_implementacion_sasisopa_procedimientos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha_implementacion',
        'procedimiento',
        'descripcion',
        'informacion',
        'observaciones',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'fecha_implementacion' => 'date',
        'informacion' => 'string',
    ];

        public function puestos()
    {
        return $this->hasMany(
            ImplementacionSasisopaProcedimientosPuesto::class,
            'id_reporte'
        );
    }

}
