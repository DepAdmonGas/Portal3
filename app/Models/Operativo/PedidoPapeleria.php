<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoPapeleria extends Model
{
    protected $table = 'op_pedido_papeleria';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'depto',
        'id_personal',
        'fecha',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'depto' => 'integer',
        'id_personal' => 'integer',
        'status' => 'integer',
        'fecha' => 'datetime'
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
        return $this->hasMany(PedidoPapeleriaDetalle::class, 'id_pedido', 'id');
    }

    public function firmas()
    {
        return $this->hasMany(PedidoPapeleriaFirma::class, 'id_pedido', 'id');
    }

    public function token()
    {
        return $this->hasMany(PedidoPapeleriaToken::class, 'id_pedido', 'id');
    }

}

