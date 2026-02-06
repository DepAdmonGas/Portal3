<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AceiteLubricante extends Model
{
    protected $table = 'op_aceites_lubricantes';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No usa created_at ni updated_at

    protected $fillable = [
        'idreporte_dia',
        'id_aceite',
        'concepto',
        'cantidad',
        'precio_unitario'
    ];

    protected $casts = [
        'idreporte_dia' => 'integer',
        'id_aceite' => 'integer',
        'cantidad' => 'integer',
        'precio_unitario' => 'double'
    ];
}
