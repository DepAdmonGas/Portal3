<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class RecepcionDescargarFirma extends Model
{
    protected $table = 'tb_recepcion_descargar_firma';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_recepcion_descarga',
        'id_usuario',
        'tipo_firma',
        'imagen_firma',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_recepcion_descarga' => 'integer',
        'id_usuario' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }

    public function recepcion()
    {
        return $this->belongsTo(
            RecepcionDescargar::class,
            'id_recepcion_descarga'
        );
    }

}
