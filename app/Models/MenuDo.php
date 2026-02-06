<?php

namespace App\Models;

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
}
