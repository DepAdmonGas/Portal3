<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Grupo extends Model{
    protected $table = 'tb_grupos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'estatus' => 'integer',
    ];

}