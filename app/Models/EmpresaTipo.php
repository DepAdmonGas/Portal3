<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaTipo extends Model
{
protected $table = 'tb_empresa_tipo';

protected $primaryKey = 'id';

public $incrementing = true;

protected $keyType = 'int';

public $timestamps = false;

protected $fillable = [
'tipo',
'estatus'
];

protected $casts = [
'id' => 'int'
];

public function empresa()
{
return $this->hasMany(Empresa::class, 'id_tipo');
}

}
