<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionObraFormato12Procedimiento extends Model
{
    protected $table = 'tb_requisicion_obra_formato_12_procedimiento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'detalle',
        'valor'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'valor' => 'int'
    ];
}
