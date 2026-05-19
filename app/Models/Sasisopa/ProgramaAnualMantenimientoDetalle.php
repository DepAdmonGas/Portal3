<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualMantenimientoDetalle extends Model
{
    protected $table = 'po_programa_anual_mantenimiento_detalle';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [

        'id_programa_fecha',
        'id_mantenimiento',
        'ultimafecha',

        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',

        'estado',
    ];

    protected $casts = [

        'id' => 'integer',
        'id_programa_fecha' => 'integer',
        'id_mantenimiento' => 'integer',
        'estado' => 'integer',
    ];

    public function mantenimiento()
    {
        return $this->belongsTo(
            MantenimientoLista::class,
            'id_mantenimiento'
        );
    }
}