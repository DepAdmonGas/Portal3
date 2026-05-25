<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'op_cliente';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at ni updated_at

    protected $fillable = [
        'id_estacion',
        'cuenta',
        'cliente',
        'tipo',
        'rfc',
        'doc_cc',
        'doc_ac',
        'doc_cd',
        'doc_io',
        'doc_rfc',
        'doc_oc',
        'doc_np',
        'estado'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'estado'      => 'integer',
    ];
}

