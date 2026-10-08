<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class OpOrdenCompraFirma extends Model
{
    protected $table = 'op_orden_compra_firma';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_usuario',
        'tipo_firma',
        'firma',
        'fecha'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id');
    }
}