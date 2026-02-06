<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoCorrectivoFirma extends Model
{
    protected $table = 'po_mantenimiento_correctivo_firma';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_usuario',
        'nombre',
        'tipo_firma',
        'imagen_firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mantenimiento' => 'integer',
        'id_usuario' => 'integer',
    ];

}
