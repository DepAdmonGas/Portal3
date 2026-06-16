<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class RequisicionObraFormato15 extends Model
{
    protected $table = 'tb_requisicion_obra_formato_15';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'fecha',
        'archivo',
        'fecha_lv',
        'hora_lv',
        'id_usuario',
        'pregunta1',
        'pregunta2',
        'pregunta3',
        'pregunta4',
        'pregunta5'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'id_usuario' => 'int',
        'pregunta1' => 'int',
        'pregunta2' => 'int',
        'pregunta3' => 'int',
        'pregunta4' => 'int',
        'pregunta5' => 'int',
        'fecha' => 'datetime',
        'fecha_lv' => 'date',
        'hora_lv' => 'datetime:H:i:s'
    ];

     public function supervisor()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }
}
