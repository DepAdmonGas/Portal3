<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sasisopa\CursoCalendario;
use App\Core\TwoFactorAuth;


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
        'estatus',
        // ============================================================
        // SECURITY: Campos para 2FA (BAJO #32)
        // ============================================================
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_backup_codes'
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_backup_codes'
    ];

    // ============================================================
    // SECURITY: Casts para 2FA (BAJO #32)
    // ============================================================
    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'two_factor_backup_codes' => 'array'
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

    public static function buscarFirma(string $usuario)
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

public function capacitaciones()
    {
        return $this->hasMany(CursoCalendario::class, 'id_personal');
    }

    // ============================================================
    // SECURITY: Métodos para 2FA (BAJO #32)
    // ============================================================

    /**
     * Verifica si el usuario tiene 2FA habilitado
     * 
     * @return bool True si 2FA está habilitado
     */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) ($this->two_factor_enabled ?? false);
    }

    /**
     * Verifica el código TOTP
     * 
     * @param string $code Código TOTP a verificar
     * @return bool True si el código es válido
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_secret) {
            return false;
        }
        
        return TwoFactorAuth::verifyCode($this->two_factor_secret, $code);
    }

    /**
     * Verifica un código de respaldo
     * 
     * @param string $code Código de respaldo
     * @return bool True si el código es válido y no usado
     */
    public function verifyBackupCode(string $code): bool
    {
        if (!$this->two_factor_backup_codes) {
            return false;
        }
        
        $index = TwoFactorAuth::verifyBackupCode($code, $this->two_factor_backup_codes);
        
        if ($index !== null) {
            // Marcar código como usado
            $codes = $this->two_factor_backup_codes;
            $codes[$index]['used'] = true;
            $this->two_factor_backup_codes = $codes;
            $this->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * Habilita 2FA para el usuario
     * 
     * @param string $secret Secreto TOTP
     * @return self
     */
    public function enableTwoFactor(string $secret): self
    {
        $this->two_factor_secret = $secret;
        $this->two_factor_enabled = true;
        $this->two_factor_backup_codes = TwoFactorAuth::generateBackupCodes();
        $this->save();
        
        return $this;
    }

    /**
     * Deshabilita 2FA para el usuario
     * 
     * @return self
     */
    public function disableTwoFactor(): self
    {
        $this->two_factor_secret = null;
        $this->two_factor_enabled = false;
        $this->two_factor_backup_codes = null;
        $this->save();
        
        return $this;
    }

    /**
     * Genera la URL para el código QR de configuración
     * 
     * @param string $issuer Nombre de la aplicación
     * @return string URL otpauth://
     */
    public function getTwoFactorQrCodeUrl(string $issuer = 'Portal3'): string
    {
        if (!$this->two_factor_secret) {
            return '';
        }
        
        return TwoFactorAuth::getQrCodeUrl(
            $this->two_factor_secret,
            $issuer,
            $this->email ?? $this->usuario
        );
    }

}


