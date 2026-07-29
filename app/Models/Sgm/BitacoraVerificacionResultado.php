<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class BitacoraVerificacionResultado extends Model
{
    protected $table = 'sgm_bitacora_verificacion_resultado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'id_lista',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'id_lista' => 'integer',
        'resultado' => 'string',
    ];

    public function lista()
    {
        return $this->belongsTo(
            BitacoraVerificacionLista::class,
            'id_lista'
        );
    }
}
