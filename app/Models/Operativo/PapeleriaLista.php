<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PapeleriaLista extends Model
{
    protected $table = 'op_papeleria_lista';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'producto',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'producto' => 'string',
        'estatus' => 'integer'
    ];
}
