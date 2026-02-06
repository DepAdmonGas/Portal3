<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecepcionDescargarEvidencia extends Model
{
    protected $table = 'tb_recepcion_descargar_evidencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_recepcion_descarga',
        'fecha',
        'ruta',
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_recepcion_descarga' => 'integer',
        'fecha' => 'datetime',
    ];

}
