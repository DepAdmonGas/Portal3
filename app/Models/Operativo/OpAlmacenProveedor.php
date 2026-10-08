<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpAlmacenProveedor extends Model
{
    protected $table = 'op_almacen_proveedores';
    public $timestamps = false;

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

    public function documentos()
    {
        return $this->hasMany(OpAlmacenProveedorDocumento::class, 'id_proveedor', 'id');
    }
}