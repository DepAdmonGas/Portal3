<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
protected $table = 'tb_empresa';

protected $primaryKey = 'id';

public $incrementing = true;

protected $keyType = 'int';

public $timestamps = false;

protected $fillable = [
'descripcion',
'id_tipo',
'estatus'
];

protected $casts = [
'id' => 'int'
];

public function tipoPuesto()
{
return $this->belongsTo(EmpresaTipo::class, 'id_tipo');
}

}
