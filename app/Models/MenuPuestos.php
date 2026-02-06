<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuPuestos extends Model
{
    protected $table = 'tb_menu_puestos';

    protected $primaryKey = 'id_menu_puestos';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_menu_principal',
        'id_puesto',
    ];

    protected $casts = [
        'id_menu_puestos' => 'integer',
        'id_menu_principal' => 'integer',
        'id_puesto' => 'integer',
    ];
}
