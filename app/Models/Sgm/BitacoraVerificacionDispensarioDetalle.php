<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class BitacoraVerificacionDispensarioDetalle extends Model
{

    protected $table = 'sgm_bitacora_verificacion_dispensarios_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_verificacion',
        'lado',
        'producto',
        'medida_comparar',
        'medicion_jarra_patron',
    ];


    protected $casts = [
        'id' => 'integer',
        'id_verificacion' => 'integer',
        'medida_comparar' => 'integer',
        'medicion_jarra_patron' => 'integer',
    ];


    public function verificacion()
    {
        return $this->belongsTo(
            BitacoraVerificacionDispensario::class,
            'id_verificacion',
            'id'
        );
    }
}
