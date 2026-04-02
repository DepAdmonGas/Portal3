<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ListaAsistenciaEvidencia extends Model
{
    protected $table = 'tb_lista_asistencia_evidencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_lista_asistencia',
        'fecha_hora',
        'evidencia',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista_asistencia' => 'integer',
        'fecha_hora' => 'datetime',
    ];
}
