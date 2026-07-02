<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ReporteCreMes extends Model
{
    protected $table = 're_reporte_cre_mes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'mes',
        'year',
        'f_producto_uno',
        'f_producto_dos',
        'f_producto_tres',
        'fi_producto_uno',
        'fi_producto_dos',
        'fi_producto_tres',
        'ff_producto_uno',
        'ff_producto_dos',
        'ff_producto_tres',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'mes' => 'integer',
        'year' => 'integer',
    ];

    public function productos()
    {
        return $this->hasMany(ReporteCreProducto::class, 'id_re_mes');
    }

    public function mensajes()
    {
        return $this->hasMany(
            ReporteCreMensaje::class,
            'id_reporte'
        );
    }


}
