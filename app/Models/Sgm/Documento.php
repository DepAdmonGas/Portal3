<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $table = 'sgm_documentos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'codificacion',
        'nombre',
        'fecha_aprobacion',
        'seccion',
    ];

    protected $casts = [
        'id' => 'integer',
        'codificacion' => 'string',
        'nombre' => 'string',
        'fecha_aprobacion' => 'date',
        'seccion' => 'integer',
    ];

    public function archivos()
    {
        return $this->hasMany(ControlDocumental::class, 'id_documento');
    }
}
