<?php
namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FacturaMonederoComentario extends Model
{
protected $table = 'op_factura_monedero_comentario';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_factura',
'fecha_hora',
'id_usuario',
'comentario'
];

protected $casts = [
'id_factura' => 'integer',
'fecha_hora' => 'datetime',
'id_usuario' => 'integer'
];
}
