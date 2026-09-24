<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoPinturasComplementos extends Model
{
    protected $table = 'op_pedido_pinturas_complementos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha',
        'observaciones',
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
        return $this->hasMany(PedidoPinturasDetalle::class, 'id_pedido', 'id');
    }

    public function firmas()
    {
        return $this->hasMany(PedidoPinturasComplementosFirma::class, 'id_pedido', 'id');
    }

    public function token()
    {
        return $this->hasMany(PedidoPinturasComplementosToken::class, 'id_pedido', 'id');
    }

}

