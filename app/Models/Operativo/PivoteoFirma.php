<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PivoteoFirma extends Model
{
    protected $table = 'op_pivoteo_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no usa created_at ni updated_at

    protected $fillable = [
        'id_pivoteo',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pivoteo' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];


}
