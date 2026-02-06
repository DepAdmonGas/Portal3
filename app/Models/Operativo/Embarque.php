<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embarque extends Model
{
    protected $table = 'op_embarques';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha',
        'embarque',
        'documento',
        'documentocv',
        'medicionnn',
        'medicioncl',
        'importef',
        'merma',
        'nom_transporte',
        'pdf',
        'xml',
        'comprobante_p',
        'producto',
        'chofer',
        'unidad',
        'bruto',
        'neto',
        'nc_pdf',
        'nc_xml',
        'comPDF',
        'comXML',
        'precio_litro',
        'tad',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha' => 'date',
        'embarque' => 'string',
        'documento' => 'string',
        'documentocv' => 'string',
        'medicionnn' => 'double',
        'medicioncl' => 'double',
        'importef' => 'double',
        'merma' => 'double',
        'nom_transporte' => 'string',
        'pdf' => 'string',
        'xml' => 'string',
        'comprobante_p' => 'string',
        'producto' => 'string',
        'chofer' => 'string',
        'unidad' => 'string',
        'bruto' => 'integer',
        'neto' => 'integer',
        'nc_pdf' => 'string',
        'nc_xml' => 'string',
        'comPDF' => 'string',
        'comXML' => 'string',
        'precio_litro' => 'double',
        'tad' => 'string',
    ];
}
