<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodicidadMtto extends Model
{
    protected $table = 'ds_periodicidad_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at ni updated_at

    protected $fillable = [
        'descripcion',
        'tipo',
    ];
}
