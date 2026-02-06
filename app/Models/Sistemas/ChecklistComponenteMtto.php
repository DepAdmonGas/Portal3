<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistComponenteMtto extends Model
{
    protected $table = 'ds_checklist_componentes_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_checklist_refacciones_mtto',
        'id_componente',
        'fecha_modificacion',
        'puntaje',
        'notas',
    ];

    protected $casts = [
        'id_checklist_refacciones_mtto' => 'integer',
        'id_componente' => 'integer',
        'puntaje' => 'integer',
        'fecha_modificacion' => 'date',
    ];

}
