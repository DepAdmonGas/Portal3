<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Junta extends Model
{
    protected $table = 'juntas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idPuesto',
        'idUsuario',
        'descripcion',
        'personalA',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'estatus',
        'deletedBy',
    ];

    protected $casts = [
        'idPuesto' => 'integer',
        'idUsuario' => 'integer',
        'deletedBy' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_termino' => 'datetime:H:i',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'idUsuario',
            'id'
        );
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(
            JuntasComentario::class,
            'id_junta',
            'id'
        );
    }
}
