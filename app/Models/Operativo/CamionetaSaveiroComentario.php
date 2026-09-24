<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class CamionetaSaveiroComentario extends Model
{
    protected $table = 'op_camioneta_saveiro_comentarios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_documento',
        'id_usuario',
        'comentario',
        'fecha_hora'
    ];

    protected $casts = [
        'id'           => 'integer',
        'id_documento' => 'integer',
        'id_usuario'   => 'integer',
        'fecha_hora'   => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id');
    }

    public function documento()
    {
        return $this->belongsTo(CamionetaSaveiroDocumento::class, 'id_documento', 'id');
    }
}