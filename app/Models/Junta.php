<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Junta extends Model
{
    protected $table = 'juntas';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No existen created_at ni updated_at

    protected $fillable = [
        'idPuesto',
        'idUsuario',
        'descripcion',
        'personalA',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'estatus',
        'deletedBy'
    ];

    protected $casts = [
        'idPuesto' => 'integer',
        'idUsuario' => 'integer',
        'deletedBy' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_termino' => 'datetime:H:i',
    ];
}
