<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Sasisopa\RequisitosLegalesCalendario;

class Estacion extends Model
{
    protected $table = 'tb_estaciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'numlista',
        'nombre',
        'es',
        'permisocre',
        'razonsocial',
        'rfc',
        'direccioncompleta',
        'di_estado',
        'di_municipio',
        'apoderado_legal',
        'firma',
        'politica',
        'mision',
        'vision',
        'franquicia',
        'producto_uno',
        'producto_dos',
        'producto_tres',
        'sasisopa',
        'fecha_autorizacion',
        'organigrama',
        'volumetrico',
        'latitud',
        'longitud',
        'distmax',
        'ubicacion',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'numlista' => 'integer',
        'latitud' => 'float',
        'longitud' => 'float',
        'distmax' => 'float',
        'fecha_autorizacion' => 'date',
        'ubicacion' => 'integer',
        'estatus' => 'integer',
    ];

    public static function siguienteNumlista(): int
    {
        $ultimo = self::select('numlista')
            ->orderByDesc('numlista')
            ->lockForUpdate()
            ->value('numlista');

        return ($ultimo ?? 0) + 1;
    }

    public static function guardar(array $data): self
    {
        return self::create($data);
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_gas');
    }

    public function calendarios()
    {
        return $this->hasMany(
            RequisitosLegalesCalendario::class,
            'id_estacion'
        );
    }
}
