<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class AuditoriaExterna extends Model
{
    protected $table = 'tb_auditoria_externa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fechacreacion',
        'prestador_servicio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fechacreacion' => 'datetime',
        'prestador_servicio' => 'string',
    ];

        public function formatos()
    {
        return $this->hasMany(
            AuditoriaExternaFormato::class,
            'id_auditoria',
            'id'
        );
    }

    public function asea()
    {
        return $this->hasMany(
            AuditoriaExternaAsea::class,
            'id_auditoria',
            'id'
        );
    }
}
