<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificar extends Model
{
    protected $table = 'po_mantenimiento_verificar';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'folio',
        'id_estacion',
        'id_equipo',
        'fechacreacion',
        'horacreacion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'folio' => 'integer',
        'id_estacion' => 'integer',
        'id_equipo' => 'integer',
        'estado' => 'integer',
        'fechacreacion' => 'date',
        'horacreacion' => 'datetime:H:i:s',
    ];

    public function mantenimientoLista()
    {
        return $this->belongsTo(
            MantenimientoLista::class,
            'id_equipo',
            'id'
        );
    }

    public function detalles() 
    { 
        return $this->hasMany(
            MantenimientoVerificarDetalle::class, 
            'id_verificar' 
        ); 
    }

    public function firmas() { 
        return $this->hasMany(
            MantenimientoVerificarFirma::class, 
            'id_verificar' 
            ); 
    }

    public function evidencias() { 
        return $this->hasMany(
            MantenimientoVerificarEvidencia::class, 
            'id_mantenimiento' 
            ); 
    }

    public function fechaFin() { 
        return $this->hasOne(
            MantenimientoVerificarFechaFin::class, 
            'id_verificar' 
            ); 
    }

    public function equipo() { 
        return $this->belongsTo(
            MantenimientoLista::class, 
            'id_equipo' 
            ); 
    }

    public function tirillas()
    {
        return $this->hasMany(
            MantenimientoVerificarTirillaInventario::class,
            'id_verificar'
        );
    }

    public function pruebasHermeticidad()
    {
        return $this->hasMany(
            MantenimientoPruebaHermeticidad::class,
            'id_verificar'
        );
    }

    public function detectoresHumo()
    {
        return $this->hasMany(
            MantenimientoDetectorHumoDetalle::class,
            'id_verificar'
        );
    }

    public function extintores()
    {
        return $this->hasMany(
            ExtintorEstacionDetalle::class,
            'id_verificar'
        );
    }

    public function tanques()
    {
        return $this->hasMany(
            MantenimientoVerificarTanque::class,
            'id_verificar'
        );
    }


}
