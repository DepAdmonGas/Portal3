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

   public function roles()
    {
        return $this->belongsToMany(Puestos::class, 'roles_menus', 'menu_id', 'puesto_id');
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuarios_menus', 'menu_id', 'usuario_id')
                    ->withPivot('tipo');
    }

    public function categoria()
    {
        return $this->belongsTo(MenuCategoria::class, 'categoria_id');
    }

    public function hijos()
    {
        return $this->hasMany(Menu::class, 'padre_id');
    }
}