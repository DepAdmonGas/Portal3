<?php
namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Estacion;

class InvestigacionIncidenteAccidenteNo extends Model
{
    protected $table = 'tb_investigacion_incidente_accidente_no';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'id_usuario',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'estatus' => 'integer',
        'fecha' => 'date',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id'
        );
    }

    public function estacion()
    {
        return $this->belongsTo(
            Estacion::class,
            'id_estacion',
            'id'
        );
    }
}
