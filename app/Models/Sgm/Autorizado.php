<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class Autorizado extends Model
{
    protected $table = 'sgm_autorizado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_hora',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
        'estado' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id'
        );
    }
}
