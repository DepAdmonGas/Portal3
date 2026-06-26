<?php

namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class ImplementacionSasisopa extends Model
{
    protected $table = 'tb_implementacion_sasisopa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha_hora',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }

    public function procedimientos()
    {
        return $this->hasMany(
            ImplementacionSasisopaProcedimientos::class,
            'id_reporte'
        );
    }


    
}
