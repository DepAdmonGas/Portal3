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

    public function requisito()
    {
        return $this->belongsTo(RequisitosLegalesLista::class, 'id_requisito_legal');
    }

    public function matriz()
    {
        return $this->hasMany(RequisitosLegalesMatriz::class, 'idcalendario', 'id');
    }

    public function matrizReciente()
    {
        return $this->hasOne(RequisitosLegalesMatriz::class, 'idcalendario', 'id')
            ->latestOfMany('fecha_emision');
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
            ->whereHas('matrizReciente')
            ->get();

        foreach ($calendarios as $calendario) {

            $matriz = $calendario->matrizReciente;

            $acuse = trim($matriz->acusepdf ?? '');
            $requisito = trim($matriz->requisitolegalpdf ?? '');

            $cumplimiento = 0;
            $finalizado = 0;

            if ($acuse !== '') {
                $cumplimiento += 50;
            }

            if ($requisito !== '') {
                $cumplimiento = 100;
                $finalizado = 1;
            }

            $ToReFin += $finalizado;
            $TotalCmp += $cumplimiento;
        }

        $total = $calendarios->count();

        return [
            "ToReFin" => $ToReFin,
            "ToRe" => $total,
            "Cumplimiento" => $total > 0
                ? round($TotalCmp / $total, 0)
                : 0
        ];
    }

    //--------------------------------------------------------------------

    public static function NivelGobierno($NGobierno, $IDEstacion)
    {
        $calendarios = self::with(['requisito', 'matrizReciente'])
            ->where('id_estacion', $IDEstacion)
            ->where('nivel_gobierno', $NGobierno)
            ->where('estado', 1)
            ->get();

        return $calendarios->map(function ($item) {

            if ($item->id_requisito_legal == 0) {
                $dependencia = 'S/I';
                $requisito = $item->requisito_legal;
            } else {
                $dependencia = optional($item->requisito)->dependencia ?? 'S/I';
                $requisito = optional($item->requisito)->permiso ?? 'S/I';
            }

            $matriz = $item->matrizReciente;

            $fechaEmision = $matriz?->fecha_emision
            ? $matriz->fecha_emision->format('Y-m-d')
            : null;

                $fechaVencimiento = $matriz?->fecha_vencimiento
                ? $matriz->fecha_vencimiento->format('Y-m-d')
                : null;

            $acuse = trim($matriz->acusepdf ?? '');
            $req = trim($matriz->requisitolegalpdf ?? '');

            if ($acuse === '' && $req === '') {
                $cumplimiento = 0;
            } elseif ($acuse !== '' && $req === '') {
                $cumplimiento = 50;
            } else {
                $cumplimiento = 100;
            }

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
                'id' => $item->id,
                'dependencia' => $dependencia,
                'permiso' => $requisito,
                'vigencia' => $item->vigencia,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'acuse_file' => $acuse,
                'requisito_file' => $req,
                'renovacion' => $renovacion,
                'cumplimiento' => $cumplimiento
            ];
        });
    }

}
