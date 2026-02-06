<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteCreProducto extends Model
{
    protected $table = 're_reporte_cre_producto';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_re_mes',
        'fecha',
        'producto',
        'volumen_inicial',
        'volumen_venta',
        'volumen_final',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_re_mes' => 'integer',
        'volumen_inicial' => 'float',
        'volumen_venta' => 'float',
        'volumen_final' => 'float',
        'fecha' => 'date',
    ];

    public function mes()
    {
        return $this->belongsTo(ReporteCreMes::class, 'id_re_mes');
    }

    public function pipas()
    {
        return $this->hasMany(ReporteCrePipa::class, 'id_re_producto');
    }
}
