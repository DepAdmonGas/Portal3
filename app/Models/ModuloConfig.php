<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloConfig extends Model
{
    protected $table = 'tb_modulos_config';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'modulo_key',
        'tipo',
        'estaciones_soportadas',
        'departamentos_soportados',
        'tipo_departamento',
        'allow_all',
        'placeholder',
        'activo',
    ];

    protected $casts = [
        'id' => 'integer',
        'estaciones_soportadas' => 'array',
        'departamentos_soportados' => 'array',
        'allow_all' => 'boolean',
        'activo' => 'boolean',
    ];
}
