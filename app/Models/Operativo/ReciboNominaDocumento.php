<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaDocumento extends Model
{
    protected $table = 'op_recibo_nomina_documento';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // No usa created_at / updated_at

    protected $fillable = [
        'id_nomina',
        'id_usuario',
        'fecha',
        'documento'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_nomina' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
    ];


}
