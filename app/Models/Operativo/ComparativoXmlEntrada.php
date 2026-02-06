<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoXmlEntrada extends Model
{
    protected $table = 'op_comparativo_xml_entradas';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at / updated_at

    protected $fillable = [
        'id_usuario',
        'id_estacion',
        'fecha_hora',
        'year'
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_estacion'=> 'integer',
        'fecha_hora' => 'datetime',
        'year'       => 'integer'
    ];

}
