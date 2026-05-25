<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RecepcionDescargar extends Model
{
    protected $table = 'tb_recepcion_descargar';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'folio',
        'fecha',
        'hora_llegada',
        'hora_salida',
        'tiempo_descarga',
        'linea_transporte',
        'no_remolque',
        'placa',
        'operador',
        'no_remision',
        'no_factura',
        'litros_compra',
        'producto',
        'sello_noserie',
        'manometro',
        'temperatura',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'folio' => 'integer',
        'fecha' => 'date',
        'hora_llegada' => 'datetime:H:i:s',
        'hora_salida' => 'datetime:H:i:s',
        'litros_compra' => 'double',
        'estado' => 'integer',
    ];

    public function tanques()
    {
        return $this->hasMany(
            RecepcionDescargarTanque::class,
            'id_recepcion_descarga'
        );
    }

    public function sellos()
    {
        return $this->hasMany(
            RecepcionDescargarSellos::class,
            'id_recepcion_descarga'
        );
    }

    public function evidencias()
    {
        return $this->hasMany(
            RecepcionDescargarEvidencia::class,
            'id_recepcion_descarga'
        );
    }

    public function firmas()
    {
        return $this->hasMany(
            RecepcionDescargarFirma::class,
            'id_recepcion_descarga'
        );
    }
    
}
