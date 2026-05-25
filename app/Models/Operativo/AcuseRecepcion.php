<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AcuseRecepcion extends Model
{
    protected $table = 'op_acuse_recepcion';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at/updated_at estÃ¡ndar

    protected $fillable = [
        'id_personal',
        'fecha_creacion',
        'empresa',
        'personal_entrega',
        'nombre_recibe',
        'fecha',
        'estado'
    ];

    protected $casts = [
        'id_personal' => 'integer',
        'personal_entrega' => 'integer',
        'estado' => 'integer',
        'fecha_creacion' => 'datetime',
        'fecha' => 'datetime'
    ];

}

