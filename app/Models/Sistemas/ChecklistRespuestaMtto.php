<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistRespuestaMtto extends Model
{
    protected $table = 'ds_checklist_respuestas_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'id_checklist_refaccion',
        'id_checklist_pregunta',
        'respuesta',
    ];

    protected $casts = [
        'id_checklist_refaccion' => 'integer',
        'id_checklist_pregunta' => 'integer',
    ];


}
