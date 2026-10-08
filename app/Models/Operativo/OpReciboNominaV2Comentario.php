<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class OpReciboNominaV2Comentario extends Model
{
    protected $table = 'op_recibo_nomina_v2_comentarios';
    public $timestamps = false;

    protected $fillable = [
        'id_nomina',
        'id_usuario',
        'comentario',
        'fecha_hora'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id');
    }
}