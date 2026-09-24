<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CamionetaSaveiroDocumento extends Model
{
    protected $table = 'op_camioneta_saveiro_documentacion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'fecha',
        'descripcion',
        'archivo'
    ];

    protected $casts = [
        'id'    => 'integer',
        'fecha' => 'date:Y-m-d'
    ];

    public function comentarios()
    {
        return $this->hasMany(CamionetaSaveiroComentario::class, 'id_documento', 'id')
                    ->orderBy('id', 'asc');
    }
}