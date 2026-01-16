<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Puestos extends Model
{
    protected $table = 'tb_puestos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tipo_puesto',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'estatus' => 'integer',
    ];

     public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_puesto');
    }
}
