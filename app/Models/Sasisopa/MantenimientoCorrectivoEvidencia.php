<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoCorrectivoEvidencia extends Model
{
    protected $table = 'po_mantenimiento_correctivo_evidencia';
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
    ];

}
