<?php

namespace App\Models\Operativo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Usuario;

class SolicitudChequeFirma extends Model
{
protected $table = 'op_solicitud_cheque_firma';
protected $primaryKey = 'id';
public $timestamps = false;

protected $fillable = [
'id_solicitud',
'id_usuario',
'fecha',
'tipo_firma',
'firma'
];

protected $casts = [
'id' => 'integer',
'id_solicitud' => 'integer',
'id_usuario' => 'integer',
'fecha' => 'datetime'
];

public function usuario(): BelongsTo
{
return $this->belongsTo(Usuario::class, 'id_usuario');
}
}

