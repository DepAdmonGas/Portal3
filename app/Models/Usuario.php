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
        'fecha_nacimiento',
        'estado_civil',
        'seguro_social',
        'domicilio',
        'firma',
        'bitacora_app',
        'fecha_ingreso',
        'responsabilidad_sgm',
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

    public function familiares()
    {
        return $this->hasMany(UsuariosFamiliares::class, 'id_usuario');
    }

    public function formaciones()
    {
        return $this->hasMany(UsuariosFormacionAcademica::class, 'id_usuario');
    }

    public function experiencias()
    {
        return $this->hasMany(UsuariosExperienciaLaboral::class, 'id_usuario');
    }

    public function experienciaEmpresa()
    {
        return $this->hasMany(UsuariosExperienciaEmpresaGrupo::class, 'id_usuario');
    }

    public function getPorcentajeCumplimientoAttribute()
    {
        $total = 0;
        $totalCampos = 9;

        // campos base
        if (!empty($this->email)) $total++;
        if (!empty($this->telefono)) $total++;
        if (!empty($this->fecha_nacimiento) && $this->fecha_nacimiento !== '0000-00-00') $total++;
        if (!empty($this->estado_civil)) $total++;
        if (!empty($this->seguro_social)) $total++;
        if (!empty($this->domicilio)) $total++;

        // relaciones
        if ($this->familiares()->exists()) $total++;
        if ($this->formaciones()->exists()) $total++;
        if ($this->experiencias()->exists()) $total++;

        return round(($total / $totalCampos) * 100, 2);
    }

}
