<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuPrincipal extends Model
{
    protected $table = 'tb_menu_principal';

    protected $primaryKey = 'id_menu_principal';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'elemento_menu',
        'ruta_menu',
    ];

    protected $casts = [
        'id_menu_principal' => 'integer',
    ];
}
