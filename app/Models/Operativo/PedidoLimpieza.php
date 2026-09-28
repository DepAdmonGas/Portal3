<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpieza extends Model
{
    protected $table = 'op_pedido_limpieza';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'fecha' => 'datetime',
        'status' => 'integer'
    ];

    public function estacion()
    {
        return $this->belongsTo(\App\Models\Estacion::class, 'id_estacion', 'id');
    }

    public function personal()
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'id_personal', 'id');
    }

    public function detalle()
    {
        return $this->hasMany(PedidoLimpiezaDetalle::class, 'id_pedido', 'id');
    }

    public function firmas()
    {
        return $this->hasMany(PedidoLimpiezaFirma::class, 'id_pedido', 'id');
    }

    public function tokens()
    {
        return $this->hasMany(PedidoLimpiezaToken::class, 'id_pedido', 'id');
    }
}