<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RequisicionObraFormato14 extends Model
{
    protected $table = 'tb_requisicion_obra_formato_14';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'fecha',
        'archivo'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'fecha' => 'datetime'
    ];
}
