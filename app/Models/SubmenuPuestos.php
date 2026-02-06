<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmenuPuestos extends Model
{
    protected $table = 'tb_submenu_puestos';

    protected $primaryKey = 'id_submenu_puestos';

    public $timestamps = false;

    protected $fillable = [
        'id_submenu_do',
        'id_puesto'
    ];

    protected $casts = [
        'id_submenu_puestos' => 'int',
        'id_submenu_do' => 'int',
        'id_puesto' => 'int'
    ];
}
