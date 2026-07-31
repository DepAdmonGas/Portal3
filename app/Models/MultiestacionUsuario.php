<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiestacionUsuario extends Model
{
    protected $table = 'tb_multiestacion_usuario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'estaciones',
        'departamentos_puestos',
        'departamentos_localidades',
        'activo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'estaciones' => 'array',
        'departamentos_puestos' => 'array',
        'departamentos_localidades' => 'array',
        'activo' => 'boolean',
    ];
}
