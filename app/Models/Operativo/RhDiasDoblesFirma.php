<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class RhDiasDoblesFirma extends Model
{
    protected $table = 'op_rh_dias_dobles_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formato',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formato' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
