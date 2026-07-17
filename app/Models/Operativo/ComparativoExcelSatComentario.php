<?php
namespace App\Models\Operativo;
use Illuminate\Database\Eloquent\Model;

class ComparativoExcelSatComentario extends Model
{
protected $table = 'op_comparativo_excel_sat_comentario';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;
protected $fillable = [
'id_estacion', 'year', 'mes', 'id_usuario', 'comentario', 'fecha_hora'
];
protected $casts = [
'id_estacion' => 'integer', 'year' => 'integer', 'mes' => 'integer',
'id_usuario' => 'integer', 'fecha_hora' => 'datetime'
];
}
