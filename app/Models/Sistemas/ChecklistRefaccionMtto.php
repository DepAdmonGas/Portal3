<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistRefaccionMtto extends Model
{
    protected $table = 'ds_checklist_refacciones_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'id_calendario',
        'id_refaccion',
        'fecha_modificacion',
        'puntaje',
        'notas',
    ];

    protected $casts = [
        'id_calendario' => 'integer',
        'id_refaccion' => 'integer',
        'puntaje' => 'integer',
        'fecha_modificacion' => 'date',
    ];

}
