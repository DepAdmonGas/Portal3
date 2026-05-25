<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CamionetaSaveiroDocumentacion extends Model
{
    protected $table = 'op_camioneta_saveiro_documentacion';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'tipo',
        'fecha',
        'descripcion',
        'archivo'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}

