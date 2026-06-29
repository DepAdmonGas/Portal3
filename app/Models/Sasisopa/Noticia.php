<?php

namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class Noticia extends Model
{
    protected $table = 'no_noticias';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'titulo',
        'detalle',
        'fecha_hora',
        'url',
        'estado',
        'alerta',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
        'estado' => 'integer',
        'alerta' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }
}