<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AlmacenProveedor extends Model
{
    protected $table = 'op_almacen_proveedores';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no incluye created_at / updated_at

    protected $fillable = [
        'folio',
        'fecha',
        'razon_social',
        'actividad_economica',
        'email',
        'rfc',
        'ciudad',
        'telefono_1',
        'telefono_2',
        'direccion',
        'beneficiario',
        'banco',
        'metodo_pago',
        'cfdi',
        'moneda',
        'forma_pago',
        'descripcion',
        'status'
    ];

    protected $casts = [
        'folio' => 'integer',
        'rfc' => 'integer',
        'status' => 'integer',
        'fecha' => 'date'
    ];
}

