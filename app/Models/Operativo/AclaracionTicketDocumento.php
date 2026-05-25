<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AclaracionTicketDocumento extends Model
{
    protected $table = 'op_aclaracion_ticket_documento';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No usa created_at ni updated_at estÃ¡ndar

    protected $fillable = [
        'id_aclaracion',
        'id_responsable',
        'archivo',
        'fecha'
    ];

    protected $casts = [
        'id_aclaracion' => 'integer',
        'id_responsable' => 'integer',
        'fecha' => 'datetime'
    ];

}

