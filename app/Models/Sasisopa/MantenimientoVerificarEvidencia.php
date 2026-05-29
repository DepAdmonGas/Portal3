<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificarEvidencia extends Model
{
    protected $table = 'po_mantenimiento_verificar_evidencias';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'url',
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mantenimiento' => 'integer',
        'url' => 'string',
        'nombre' => 'string',
    ];

}
