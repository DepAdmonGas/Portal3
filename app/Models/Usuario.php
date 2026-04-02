<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'tb_usuarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'nombre',
        'email',
        'telefono',
        'id_gas',
        'id_puesto',
        'usuario',
        'password',
        'estatus'
    ];

    protected $hidden = [
        'password'
    ];

    public function puesto()
    {
        return $this->belongsTo(Puestos::class, 'id_puesto');
    }

    public function estacion()
    {
        return $this->belongsTo(Estacion::class, 'id_gas');
    }

    public function scopeActivo($query)
    {
        return $query->where('estatus', 0);
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'usuarios_menus', 'usuario_id', 'menu_id')
                    ->withPivot('tipo');
    }

    public static function buscarFirma($usuario)
    {
        return self::where('nombre', 'LIKE', $usuario)
            ->where('estatus', 0)
            ->orderByDesc('id')
            ->value('firma');
    }

}
