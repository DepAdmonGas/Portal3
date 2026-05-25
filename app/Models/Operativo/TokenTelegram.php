<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

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
        'id' => 'integer',
        'id_usuario' => 'integer',
        'chat_id' => 'integer',
        'estatus' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function scopeVerified($query)
    {
        return $query->where('estatus', 1);
    }

    public function scopePending($query)
    {
        return $query->where('estatus', 0);
    }

    public function scopeExpired($query)
    {
        return $query->where('estatus', 0)
            ->where('fecha_creacion', '<', \Carbon\Carbon::now()->subMinutes(2));
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('id_usuario', $userId);
    }

    public function isExpired(): bool
    {
        if ($this->estatus == 1) return false;
        if (!$this->fecha_creacion) return true;

        $expiry = \Carbon\Carbon::parse($this->fecha_creacion)->addMinutes(2);
        return \Carbon\Carbon::now()->greaterThan($expiry);
    }

    public function isVerified(): bool
    {
        return $this->estatus == 1 && !empty($this->chat_id);
    }

    public static function generateToken(int $idUsuario): self
    {
        $token = bin2hex(random_bytes(3));

        $existing = self::where('id_usuario', $idUsuario)
            ->orderBy('id', 'desc')
            ->first();

        if ($existing && $existing->estatus == 0) {
            $existing->update([
                'token' => $token,
                'fecha_creacion' => \Carbon\Carbon::now(),
                'chat_id' => 0,
            ]);
            return $existing->fresh();
        }

        self::where('id_usuario', $idUsuario)->delete();

        return self::create([
            'id_usuario' => $idUsuario,
            'token' => $token,
            'chat_id' => 0,
            'fecha_creacion' => \Carbon\Carbon::now(),
            'estatus' => 0,
        ]);
    }

    public static function revokeAccess(int $idUsuario): void
    {
        self::where('id_usuario', $idUsuario)->delete();
    }
}
