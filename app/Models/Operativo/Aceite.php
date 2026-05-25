<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Aceite extends Model
{
    protected $table = 'op_aceites';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at ni updated_at

    protected $fillable = [
        'id_aceite',
        'concepto',
        'piezas',
        'precio',
    ];

    protected $casts = [
        'id_aceite' => 'integer',
        'piezas' => 'integer',
        'precio' => 'double',
    ];

}

