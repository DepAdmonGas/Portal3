<?php

namespace App\Models;

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
}
