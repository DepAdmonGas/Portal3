<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoPortal2025 extends Model
{
    protected $table = 'ds_eventos_portal_2025';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No usa created_at / updated_at estándar

    protected $fillable = [
        'id_usuario',
        'fecha_creacion',
        'accion',
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'fecha_creacion' => 'datetime',
    ];

    // RELACIÓN (si usas tabla users)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
}
