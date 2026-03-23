<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MenuCategoria extends Model
{
    protected $table = 'menu_categorias';
    protected $fillable = ['nombre', 'icono', 'orden'];

    public function menus()
    {
        return $this->hasMany(Menu::class, 'categoria_id')->orderBy('orden');
    }
}