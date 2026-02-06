<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionChecklist extends Model
{
    protected $table = 'tb_revision_checklist';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_categoria',
        'valor',
        'foto',
        'estatus'
    ];

    protected $casts = [
        'id' => 'int',
        'no_reporte' => 'int',
        'id_categoria' => 'int',
        'estatus' => 'int'
    ];
}
