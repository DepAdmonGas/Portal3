<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhLocalidadesHorario extends Model
{
    protected $table = 'op_rh_localidades_horario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'titulo',
        'hora_entrada',
        'hora_salida'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'hora_entrada' => 'string',
        'hora_salida' => 'string'
    ];

    public function estacion()
    {
        return $this->belongsTo(Estacion::class, 'id_estacion');
    }
}

