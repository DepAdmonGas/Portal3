<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenProveedorDocumento extends Model
{
    protected $table = 'op_almacen_proveedores_documentos';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nombre',
        'fecha',
        'archivo'
    ];

    protected $casts = [
        'id_proveedor' => 'integer',
        'fecha' => 'date'
    ];
}
