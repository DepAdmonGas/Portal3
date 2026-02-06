<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcuseRecepcionDocumento extends Model
{
    protected $table = 'op_acuse_recepcion_documentos';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at/updated_at

    protected $fillable = [
        'id_acuse',
        'documento',
        'paginas',
        'original',
        'copia'
    ];

    protected $casts = [
        'id_acuse' => 'integer',
        'paginas' => 'integer',
        'original' => 'integer',
        'copia' => 'integer'
    ];

}
