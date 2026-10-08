<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpAlmacenProveedorDocumento extends Model
{
    protected $table = 'op_almacen_proveedores_documentos';
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nombre',
        'fecha',
        'archivo'
    ];

    public function proveedor()
    {
        return $this->belongsTo(OpAlmacenProveedor::class, 'id_proveedor', 'id');
    }
}