<?php

namespace App\Models;
use App\Models\Usuario;
use App\Models\Puesto;

use Illuminate\Database\Eloquent\Model;

class SubmenuDo extends Model
{
    protected $table = 'tb_submenu_do';

    protected $primaryKey = 'id_submenu_do';

    public $timestamps = false;

    protected $fillable = [
        'id_menu_do',
        'elemento_submenu_do',
        'ruta_submenu_do',
        'imagen'
    ];

    protected $casts = [
        'id_submenu_do' => 'int',
        'id_menu_do' => 'int'
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Puestos::class,
            'tb_submenu_puestos',
            'id_submenu_do',
            'id_puesto'
        );
    }

        public function usuarios()
    {
        return $this->belongsToMany(
            Usuario::class,
            'tb_submenu_usuarios',
            'id_submenu_do',
            'id_usuario'
        );
    }



}
