<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonederoEdi extends Model
{
    protected $table = 'op_monedero_edi';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_documento',
        'complemento',
        'pdf',
        'xml',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_documento' => 'integer',
        'complemento' => 'string',
        'pdf' => 'string',
        'xml' => 'string',
    ];
}
