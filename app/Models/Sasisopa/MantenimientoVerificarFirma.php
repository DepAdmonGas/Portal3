<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class MantenimientoVerificarFirma extends Model
{
    protected $table = 'po_mantenimiento_verificar_firma';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'id_usuario',
        'nombre',
        'tipo_firma',
        'imagen_firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'id_usuario' => 'integer',
        'nombre' => 'string',
        'tipo_firma' => 'string',
        'imagen_firma' => 'string',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id'
        );
    }

}
