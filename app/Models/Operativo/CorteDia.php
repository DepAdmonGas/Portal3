<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Operativo\Observacione;

class CorteDia extends Model
{
    protected $table = 'op_corte_dia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha',
        'ventas',
        'tpv',
        'monedero',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha' => 'date',
        'ventas' => 'integer',
        'tpv' => 'integer',
        'monedero' => 'integer',
    ];

    public function observaciones()
    {
        return $this->hasOne(Observacione::class, 'idreporte_dia', 'id');
    }
}
