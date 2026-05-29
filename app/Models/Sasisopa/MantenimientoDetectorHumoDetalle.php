<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\DetectorHumo;

class MantenimientoDetectorHumoDetalle extends Model
{
    protected $table = 'po_mantenimiento_detector_humo';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'id_detector',
        'revision',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'id_detector' => 'integer',
    ];

    public function detector(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DetectorHumo::class, 'id_detector', 'id');
    }
}
