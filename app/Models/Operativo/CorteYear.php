<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CorteYear extends Model
{
protected $table = 'op_corte_year';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_estacion',
'year',
];

protected $casts = [
'id' => 'integer',
'id_estacion' => 'integer',
'year' => 'integer',
];


public static function yearsByEstacion($idEstacion)
{
return self::where('id_estacion', $idEstacion)
->select('year')
->groupBy('year')
->orderBy('year', 'desc')
->pluck('year');
}

}
