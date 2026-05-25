<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CorteDiaToken extends Model
{
protected $table = 'op_corte_dia_token';

public $timestamps = false;

protected $fillable = [
'id_reportedia', 'id_usuario', 'token'
];
}
