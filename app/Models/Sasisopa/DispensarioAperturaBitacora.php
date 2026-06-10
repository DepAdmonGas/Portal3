<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class DispensarioAperturaBitacora extends Model
{
    protected $table = 'tb_dispensarios_apertura_bitacora';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_dispensario',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'lado',
        'producto',
        'clave',
        'motivo',
        'responsable',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_dispensario' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_termino' => 'string',
        'lado' => 'string',
        'producto' => 'string',
        'clave' => 'string',
        'motivo' => 'string',
        'responsable' => 'integer',
        'detalle' => 'string',
    ];

    public function dispensario()
    {
        return $this->belongsTo(
            Dispensario::class,
            'id_dispensario'
        );
    }

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'responsable'
        );
    }
}
