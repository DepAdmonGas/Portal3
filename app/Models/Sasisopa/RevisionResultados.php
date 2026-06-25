<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class RevisionResultados extends Model
{
    protected $table = 'tb_revision_resultados';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'archivo'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha_hora' => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }
}
