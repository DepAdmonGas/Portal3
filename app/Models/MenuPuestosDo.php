<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuPuestosDo extends Model
{
    protected $table = 'tb_menu_puestos_do';

    protected $primaryKey = 'id_menu_puestos_do';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_menu_do',
        'id_puesto',
    ];

    protected $casts = [
        'id_menu_puestos_do' => 'integer',
        'id_menu_do' => 'integer',
        'id_puesto' => 'integer',
    ];
}
