<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'tb_usuarios';

    protected $fillable = [
        'id',
        'nombre',
        'email',
        'telefono',
        'id_puesto',
        'usuario',
        'password',
        'estatus'
    ];

    protected $hidden = [
        'password'
    ];

    public $timestamps = false;

    public function puesto()
    {
        return $this->belongsTo(Puestos::class, 'id_puesto');
    }

    public function scopeActivo($query)
    {
        return $query->where('estatus', 0);
    }
}
