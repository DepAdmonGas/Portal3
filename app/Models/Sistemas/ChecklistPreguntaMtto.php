<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistPreguntaMtto extends Model
{
    protected $table = 'ds_checklist_preguntas_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at / updated_at

    protected $fillable = [
        'id_categoria',
        'id_equipo',
        'id_periodicidad',
        'pregunta',
        'estado',
    ];

    protected $casts = [
        'id_categoria' => 'integer',
        'id_equipo' => 'integer',
        'id_periodicidad' => 'integer',
        'estado' => 'integer',
    ];

  
}
