<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AclaracionBaucherDocumento extends Model
{
    protected $table = 'op_aclaracion_bauchers_documento';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at ni updated_at estÃ¡ndar

    protected $fillable = [
        'id_aclaracion',
        'id_responsable',
        'archivo',
        'fecha'
    ];

    protected $casts = [
        'id_aclaracion' => 'integer',
        'id_responsable' => 'integer',
        'fecha' => 'datetime',
    ];
}

