<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario;

class RhDiaDobleComentarios extends Model
{
    protected $table = 'op_rh_dia_doble_comentarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'comentario',
        'fecha_hora',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(RhDiaDobleRegistro::class, 'id_reporte');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
