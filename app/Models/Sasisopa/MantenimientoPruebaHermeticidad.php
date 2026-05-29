<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\TanqueAlmacenamiento;

class MantenimientoPruebaHermeticidad extends Model
{
    protected $table = 'po_mantenimiento_prueba_hermeticidad';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'id_tanque',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'id_tanque' => 'integer',
        'fecha' => 'date',
    ];

    public function tanque(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TanqueAlmacenamiento::class, 'id_tanque', 'id');
    }
}
