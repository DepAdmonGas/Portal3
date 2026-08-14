<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class BitacoraVerificacionDispensario extends Model
{

    protected $table = 'sgm_bitacora_verificacion_dispensarios';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;


    protected $fillable = [
        'id_programa',
        'fecha',
        'hora',
        'marca_modelo_jarra_patron',
        'capacidad',
        'jarra_patron_calibrada',
        'no_dispensario',
        'realizadopor',
    ];


    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'fecha' => 'date:Y-m-d',
        'hora' => 'datetime:H:i:s',
        'no_dispensario' => 'integer',
        'realizadopor' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(
            BitacoraVerificacionDispensarioDetalle::class,
            'id_verificacion'
        );
    }
}
