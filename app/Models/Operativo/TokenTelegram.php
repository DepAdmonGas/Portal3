<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenTelegram extends Model
{
    protected $table = 'op_token_telegram';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'token',
        'chat_id',
        'fecha_creacion',
        'estatus',
    ];

    protected $casts = [
        'id'             => 'integer',
        'id_usuario'     => 'integer',
        'chat_id'        => 'integer',
        'estatus'        => 'integer',
        'fecha_creacion' => 'datetime',
    ];
}
