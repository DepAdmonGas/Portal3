<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario;

class SolicitudChequeComentario extends Model
{
protected $table = 'op_solicitud_cheque_comentario';
protected $primaryKey = 'id';
public $timestamps = false;

protected $fillable = [
'id_solicitud',
'fecha_hora',
'id_usuario',
'comentario'
];

protected $casts = [
'id' => 'integer',
'id_solicitud' => 'integer',
'id_usuario' => 'integer',
'fecha_hora' => 'datetime'
];

public function usuario(): BelongsTo
{
return $this->belongsTo(Usuario::class, 'id_usuario');
}
}

