<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesCalendario extends Model
{
    protected $table = 'op_refacciones_calendario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'titulo', 'tipo_evento', 'fecha', 'hora', 'id_estacion', 'estado',
        'correo_responsable', 'notas', 'id_usuario', 'creado_en', 'actualizado_en',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
    ];
}
