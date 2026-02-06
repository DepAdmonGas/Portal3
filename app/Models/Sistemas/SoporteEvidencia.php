<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteEvidencia extends Model
{
    protected $table = 'ds_soporte_evidencia';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at ni updated_at

    protected $fillable = [
        'id_ticket',
        'descripcion',
        'evidencia',
    ];

    protected $casts = [
        'id_ticket' => 'integer',
    ];
}
