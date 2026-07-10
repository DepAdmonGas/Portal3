<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class SasisopaActividad extends Model
{
    protected $table = 'sa_sasisopa_actividades';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_sasisopa',
        'formato',
        'actividad',
        'periodicidad',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_sasisopa' => 'integer',
    ];

    public function sasisopa()
{
    return $this->belongsTo(
        Sasisopa::class,
        'id_sasisopa',
        'numero_sasisopa'
    );
}
}
