<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class SeguimientoAsistente extends Model
{
    protected $table = 'sgm_seguimiento_asistentes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_seguimiento',
        'id_usuario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_seguimiento' => 'integer',
        'id_usuario' => 'integer',
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
