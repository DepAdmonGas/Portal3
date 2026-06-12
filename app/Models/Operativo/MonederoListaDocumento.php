<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MonederoListaDocumento extends Model
{
protected $table = 'op_monedero_lista_documentos';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_monedero',
'descripcion',
'archivo',
];

protected $casts = [
'id' => 'integer',
'id_monedero' => 'integer',
'descripcion' => 'string',
'archivo' => 'string',
];
}
