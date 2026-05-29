<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class MantenimientoSubLista extends Model
{
    protected $table = 'po_mantenimiento_sub_lista';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

}
