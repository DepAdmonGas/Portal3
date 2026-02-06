<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteDiaArchivo extends Model
{
    protected $table = 'op_corte_dia_archivo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reportedia',
        'detalle',
        'documento',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reportedia' => 'integer',
        'detalle' => 'string',
        'documento' => 'string',
    ];
}
