<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaAsistenciaDetalle extends Model
{
    protected $table = 'tb_lista_asistencia_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_lista_asistencia',
        'usuario',
        'puesto',
        'firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista_asistencia' => 'integer',
    ];
}
