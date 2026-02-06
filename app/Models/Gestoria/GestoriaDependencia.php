<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GestoriaDependencia extends Model
{
    protected $table = 'dg_gestoria_dependencias';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'dependencia',
    ];
}
