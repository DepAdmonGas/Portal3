<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class InventarioEquipoManual extends Model
{
    protected $table = 'sgm_inventario_equipo_manual';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_equipo',
        'fecha_hora',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_equipo' => 'integer',
        'fecha_hora' => 'datetime',
        'archivo' => 'string',
    ];
}
