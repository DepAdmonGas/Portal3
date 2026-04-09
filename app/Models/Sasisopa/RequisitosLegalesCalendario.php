<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesCalendario extends Model
{
    protected $table = 'rl_requisitos_legales_calendario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_requisito_legal',
        'nivel_gobierno',
        'requisito_legal',
        'vigencia',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_requisito_legal' => 'integer',
        'enero' => 'integer',
        'febrero' => 'integer',
        'marzo' => 'integer',
        'abril' => 'integer',
        'mayo' => 'integer',
        'junio' => 'integer',
        'julio' => 'integer',
        'agosto' => 'integer',
        'septiembre' => 'integer',
        'octubre' => 'integer',
        'noviembre' => 'integer',
        'diciembre' => 'integer',
        'estado' => 'integer',
    ];

    public function matriz()
    {
        return $this->hasMany(RequisitosLegalesMatriz::class, 'idcalendario', 'id');
    }

    public function matrizReciente()
    {
        return $this->hasOne(RequisitosLegalesMatriz::class, 'idcalendario', 'id')
            ->latestOfMany('fecha_emision');
    }

    public function detalle()
    {
        return $this->belongsTo(RequisitosLegalesLista::class, 'id_requisito_legal', 'id');
    }
    

    public static function ToRequisitosTodos($id)
    {
        $niveles = ['Municipal', 'Estatal', 'Federal', 'Varios'];

        $result = [];

        foreach ($niveles as $nivel) {
            $result[$nivel] = self::ToRequisitos($id, $nivel);
        }

        return $result;
    }

    public static function ToRequisitos($id, $NGobierno)
    {
        $ToReFin = 0;
        $TotalCmp = 0;

        $calendarios = self::with('matrizReciente')
        ->where('id_estacion', $id)
        ->where('nivel_gobierno', $NGobierno)
        ->get();

        foreach ($calendarios as $calendario) {

            $matriz = $calendario->matrizReciente;

            if (!$matriz) {
                continue;
            }

            $acuse = trim($matriz->acusepdf);
            $requisito = trim($matriz->requisitolegalpdf);

            $cumplimiento = 0;
            $finalizado = 0;

            if ($acuse != "") {
                $cumplimiento += 50;
            }

            if ($requisito != "") {
                $cumplimiento = 100;
                $finalizado = 1;
            }

            $ToReFin += $finalizado;
            $TotalCmp += $cumplimiento;
        }

        return [
            "ToReFin" => $ToReFin,
            "ToRe" => $calendarios->count(),
            "Cumplimiento" => $calendarios->count() > 0
                ? round($TotalCmp / $calendarios->count(), 0)
                : 0
        ];
    }

    //--------------------------------------------------------------------

    public static function NivelGobierno($NGobierno, $IDEstacion)
    {
        $calendarios = self::with(['detalle', 'matrizReciente'])
            ->where('id_estacion', $IDEstacion)
            ->where('nivel_gobierno', $NGobierno)
            ->where('estado', 1)
            ->get();

        return $calendarios->map(function ($item) {

            // dependencia y requisito
            if ($item->id_requisito_legal == 0) {
                $dependencia = 'S/I';
                $requisito = $item->requisito_legal;
            } else {
                $dependencia = optional($item->detalle)->dependencia ?? 'S/I';
                $requisito = optional($item->detalle)->permiso ?? 'S/I';
            }

            // matriz reciente
            $matriz = $item->matrizReciente;

            $fechaEmision = ($matriz && $matriz->fecha_emision != '0000-00-00')
            ? formatearFechaCorta($matriz->fecha_emision)
            : 'S/I';

            $fechaVencimiento = ($matriz && $matriz->fecha_vencimiento != '0000-00-00')
            ? formatearFechaCorta($matriz->fecha_vencimiento)
            : 'S/I';

            // cumplimiento
            $acuse = trim(optional($matriz)->acusepdf ?? '');
            $req = trim(optional($matriz)->requisitolegalpdf ?? '');

            if ($acuse == "" && $req == "") {
                $cumplimiento = 0;
            } elseif ($acuse != "" && $req == "") {
                $cumplimiento = 50;
            } else {
                $cumplimiento = 100;
            }

            // meses
            $meses = [
                'enero' => 'Enero',
                'febrero' => 'Febrero',
                'marzo' => 'Marzo',
                'abril' => 'Abril',
                'mayo' => 'Mayo',
                'junio' => 'Junio',
                'julio' => 'Julio',
                'agosto' => 'Agosto',
                'septiembre' => 'Septiembre',
                'octubre' => 'Octubre',
                'noviembre' => 'Noviembre',
                'diciembre' => 'Diciembre',
            ];

            $renovacion = collect($meses)
                ->filter(fn($label, $campo) => $item->$campo == 1)
                ->values()
                ->implode(', ');

            return [
                'dependencia' => $dependencia,
                'requisito' => $requisito,
                'vigencia' => $item->vigencia,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'renovacion' => $renovacion,
                'cumplimiento' => $cumplimiento
            ];
        });
    }

}
