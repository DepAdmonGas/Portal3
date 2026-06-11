<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class ResiduoPeligroso extends Model
{
    protected $table = 'tb_residuos_peligrosos';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'folio',
        'nombreresiduo',
        'cantidadgenerada',
        'caracteristicas',
        'areaproceso',
        'fechaingreso',
        'fechasalida',
        'nombrerecolector',
        'numerorecolector',
        'nombretransportista',
        'numerotransportista',
        'nombredestinatario',
        'numerodestinatario',
        'procesodestinatario',
        'estado'
    ];

    protected $casts = [
        'fechaingreso' => 'date',
        'fechasalida' => 'date'
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }
}