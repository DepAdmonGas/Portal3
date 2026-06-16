<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sasisopa\RequisicionObraFormato12TrabajadorEncargado;
use App\Models\Usuario;
class RequisicionObraFormato12 extends Model
{
    protected $table = 'tb_requisicion_obra_formato_12';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'fecha',
        'archivo',
        'dia',
        'mes',
        'year',
        'municipio',
        'estado',
        'trabajo_realizar',
        'descripcion',
        'area',
        'fecha_inicio',
        'fecha_termino',
        'hora_inicio',
        'hora_termino',
        'prestador_servicio',
        'cprtp',
        'cteppc',
        'nombre_empresa',
        'nombre_responsable'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'fecha' => 'datetime',
        'dia' => 'int',
        'year' => 'int',
        'fecha_inicio' => 'datetime',
        'fecha_termino' => 'datetime',
        'hora_inicio' => 'datetime',
        'hora_termino' => 'datetime',
        'cprtp' => 'int',
        'cteppc' => 'int'
    ];

     public function procedimientos()
    {
        return $this->hasMany(
            RequisicionObraFormato12Procedimiento::class,
            'id_requisicion',
            'id'
        );
    }

    public function trabajadores()
    {
        return $this->hasMany(
            RequisicionObraFormato12TrabajadorEncargado::class,
            'id_requisicion',
            'id'
        );
    }

}
