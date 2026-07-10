<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RecepcionDescargarTanque extends Model
{
    protected $table = 'tb_recepcion_descargar_tanque';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_recepcion_descarga',
        'idlista',
        'id_tanque',
        'inventario_inicial',
        'inventario_final',
        'aditivacion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_recepcion_descarga' => 'integer',
        'idlista' => 'integer',
        'id_tanque' => 'integer',
        'inventario_inicial' => 'double',
        'inventario_final' => 'double',
    ];

    public function tanque()
    {
        return $this->belongsTo(
            TanqueAlmacenamiento::class,
            'id_tanque'
        );
    }

    public function recepcion()
    {
        return $this->belongsTo(
            RecepcionDescargar::class,
            'id_recepcion_descarga'
        );
    }
}
