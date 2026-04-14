<?php

namespace App\Models;
use App\Models\Usuario;
use App\Models\Puesto;

use Illuminate\Database\Eloquent\Model;

class MenuDo extends Model
{
    protected $table = 'tb_menu_do';

    protected $primaryKey = 'id_menu_do';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'elemento_menu_do',
        'ruta_menu_do',
        'icono',
    ];

    protected $casts = [
        'id_menu_do' => 'integer',
    ];


    public function roles()
    {
        return $this->belongsToMany(
            Puestos::class,
            'tb_menu_puestos_do',
            'id_menu_do',
            'id_puesto'
        );
    }

    
    public function usuarios()
    {
        return $this->belongsToMany(
            Usuario::class,
            'tb_menu_usuarios_do',
            'id_menu_do',
            'id_usuario'
        );
    }

    
    public function submenus()
    {
        return $this->hasMany(
            SubmenuDo::class,
            'id_menu_do',
            'id_menu_do'
        );
    }


}
