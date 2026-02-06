<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalAsistenciaIncidencia extends Model
{
    protected $table = 'op_rh_personal_asistencia_incidencia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_asistencia',
        'fecha',
        'incidencia',
        'comentario',
        'documento',
        'estado'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_asistencia' => 'integer',
        'estado' => 'integer',
        'fecha' => 'datetime',
    ];


}
