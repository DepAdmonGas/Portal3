<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ComparativoXmlDocumento extends Model
{
    protected $table = 'op_comparativo_xml_documentos';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'anexo',
        'fecha',
        'archivo',
        'mes',
        'year'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'fecha'       => 'datetime',
        'mes'         => 'integer',
        'year'        => 'integer'
    ];

}

