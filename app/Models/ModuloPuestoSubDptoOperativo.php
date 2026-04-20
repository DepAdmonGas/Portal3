<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloPuestoSubDptoOperativo extends Model
{
protected $table = 'modulos_sub_puestos_do';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'id_sub_modulo',
'id_puesto'
];

protected $casts = [
'id_sub_modulo' => 'int',
'id_puesto' => 'int'
];


}