<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class HallazgoAuditoriaEntrevistador extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_entrevistador';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'id_usuario',
        'nombre',
        'puesto',
        'area_descripcion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'id_usuario' => 'integer',
        'nombre' => 'string',
        'puesto' => 'string',
        'area_descripcion' => 'string',
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
