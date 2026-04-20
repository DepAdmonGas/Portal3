<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';

    public $timestamps = false; 

    protected $fillable = [
        'nombre',
        'clave',
        'ruta',
        'icono',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /* ==============================
     * RELACIONES
     * ============================== */

    public function roles()
    {
        return $this->belongsToMany(
            Puestos::class,
            'roles_modulos',
            'modulo_id',
            'puesto_id'
        )->withPivot(['id','leer','crear','editar','eliminar','descargar']);
    }

    public function usuarios()
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuarios_modulos',
            'modulo_id',
            'usuario_id'
        )->withPivot(['id','leer','crear','editar','eliminar','descargar']);
    }

    public function menus()
    {
        return $this->belongsToMany(
            Menu::class,
            'modulos_menu',
            'modulo_id',
            'menu_id'
        );
    }
}