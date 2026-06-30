<?php

namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;
use App\Models\Estacion;

class SasisopaConsulta extends Model
{
    protected $table = 'tb_sasisopa';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'version',
        'documento'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int'
    ];

    public function estacion()
    {
        return $this->belongsTo(
            Estacion::class,
            'id_estacion'
        );
    }
}
