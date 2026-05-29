<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ExtintorEstacionDetalle extends Model
{
    protected $table = 'po_extintores_estacion_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'id_extintor',
        'ultima_recarga',
        'manometro',
        'boquilla_descarga',
        'manguera',
        'funcionalidad',
        'observaciones',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'id_extintor' => 'integer',
        'ultima_recarga' => 'date',
    ];

    public function extintor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExtintorEstacion::class, 'id_extintor', 'id');
    }
}
