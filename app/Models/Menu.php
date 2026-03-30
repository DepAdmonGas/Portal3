<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = [
        'nombre',
        'ruta',
        'icono',
        'categoria_id',
        'padre_id',
        'orden',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    /* ==============================
     * RELACIONES
     * ============================== */

    public function roles()
    {
        return $this->belongsToMany(
            Puestos::class,
            'roles_menus',
            'menu_id',
            'puesto_id'
        );
    }

    public function usuarios()
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuarios_menus',
            'menu_id',
            'usuario_id'
        )->withPivot('tipo');
    }

    public function categoria()
    {
        return $this->belongsTo(
            MenuCategoria::class,
            'categoria_id'
        );
    }

    public function hijos()
    {
        return $this->hasMany(
            self::class,
            'padre_id'
        )->orderBy('orden');
    }

    public function padre()
    {
        return $this->belongsTo(
            self::class,
            'padre_id'
        );
    }

    public function modulos()
    {
        return $this->belongsToMany(
            Modulo::class,
            'modulos_menu',
            'menu_id',
            'modulo_id'
        );
    }

    /* ==============================
     * SCOPES (🔥 CLAVE)
     * ============================== */

    public function scopeActivo($query)
    {
        return $query->where('activo', 1);
    }

    public function scopePorModulo($query, $modulo)
    {
        return $query->whereHas('modulos', function ($q) use ($modulo) {
            $q->where('clave', $modulo);
        });
    }
}