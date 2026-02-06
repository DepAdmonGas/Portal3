<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraRHDocumento extends Model
{
    protected $table = 'op_bitacora_rrhh_documentos';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // la tabla no tiene created_at ni updated_at

    protected $fillable = [
        'id_bitacora',
        'id_usuario',
        'nombre',
        'archivo'
    ];

    protected $casts = [
        'id_bitacora' => 'integer',
        'id_usuario'  => 'integer',
    ];

}
