<?php

namespace App\Services;

use App\Models\Operativo\CorteDia;
use App\Models\Operativo\VentasDia;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;

class ResumenImpuestosService
{
    public static function getIdReporte(int $idEstacion, int $idYear, int $idMes): int
    {
        $corteMes = CorteMes::join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
            ->where('op_corte_year.id_estacion', $idEstacion)
            ->where('op_corte_year.year', $idYear)
            ->where('op_corte_mes.mes', $idMes)
            ->select('op_corte_mes.id')
            ->first();

        return $corteMes ? (int) $corteMes->id : 0;
    }

    public static function getDias(int $idEstacion, int $idYear, int $idMes): array
    {
        $dias = CorteDia::from('op_corte_dia as d')
            ->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
            ->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
            ->where('y.id_estacion', $idEstacion)
            ->where('y.year', $idYear)
            ->where('m.mes', $idMes)
            ->orderBy('d.fecha', 'asc')
            ->select('d.id as id_dia', 'd.fecha')
            ->get();

        $items = [];
        $num = 1;
        foreach ($dias as $d) {
            $items[] = [
                'num' => $num,
                'id_dia' => (int) $d->id_dia,
                'fecha' => $d->fecha,
                'fecha_formateada' => formatearFecha($d->fecha),
            ];
            $num++;
        }

        return $items;
    }

    public static function getTotales(int $idEstacion, int $idYear, int $idMes): array
    {
        $idReporte = self::getIdReporte($idEstacion, $idYear, $idMes);
        if (!$idReporte) {
            return ['items' => [], 'subtotal' => [], 'aceites' => [], 'total' => []];
        }

        $idsDia = CorteDia::where('id_mes', $idReporte)->pluck('id')->toArray();

        $productos = VentasDia::whereIn('idreporte_dia', $idsDia)
            ->select('producto', 'ieps')
            ->groupBy('producto', 'ieps')
            ->orderBy('producto', 'asc')
            ->get();

        $items = [];
        $totalVV = 0;
        $totalISI = 0;
        $totalIV2 = 0;
        $totalIEPS2 = 0;
        $totalNeto = 0;

        foreach ($productos as $p) {
            $producto = $p->producto;
            $ieps = (float) $p->ieps;

            $tolitros = 0;
            $tojarras = 0;
            $toprecio = 0;
            $precioP = 0;

            $rows = VentasDia::whereIn('idreporte_dia', $idsDia)
                ->where('producto', $producto)
                ->orderBy('id', 'asc')
                ->get();

            foreach ($rows as $r) {
                $litros = (float) $r->litros;
                $jarras = (float) $r->jarras;
                $precio = (float) $r->precio_litro;

                $tolitros += $litros;
                $tojarras += $jarras;
                $toprecio = ($toprecio + $precio) / 2;
                $precioP = $toprecio / 30.5;
            }

            $preciosiniva = ($precioP - $ieps) / 1.16;
            $iva1 = $preciosiniva * 0.16;
            $volumenv = $tolitros - $tojarras;
            $importesiniva = $volumenv * $preciosiniva;
            $iva2 = $importesiniva * 0.16;
            $ieps2 = $volumenv * $ieps;
            $total = $importesiniva + $iva2 + $ieps2;

            $totalVV += $volumenv;
            $totalISI += $importesiniva;
            $totalIV2 += $iva2;
            $totalIEPS2 += $ieps2;
            $totalNeto += $total;

            $items[] = [
                'producto' => $producto,
                'precio_publico' => $precioP,
                'ieps' => $ieps,
                'precio_sin_iva' => $preciosiniva,
                'iva_unidad' => $iva1,
                'volumen_vendido' => $volumenv,
                'importe_sin_iva' => $importesiniva,
                'iva_total' => $iva2,
                'ieps_total' => $ieps2,
                'total' => $total,
            ];
        }

        $totalAceites = 0;
        foreach ($idsDia as $idDia) {
            $aceites = AceiteLubricante::where('idreporte_dia', $idDia)->get();
            $dayAceites = 0;
            foreach ($aceites as $a) {
                $dayAceites += (float) $a->cantidad * (float) $a->precio_unitario;
            }
            $totalAceites = $dayAceites;
        }

        $aceitesSinIva = $totalAceites / 1.16;
        $aceitesIva = $aceitesSinIva * 0.16;

        $totalDiaISI = $totalISI + $aceitesSinIva;
        $totalDiaIVA = $totalIV2 + $aceitesIva;
        $totalDia = $totalNeto + $totalAceites;

        return [
            'items' => $items,
            'subtotal' => [
                'volumen' => $totalVV,
                'importe_sin_iva' => $totalISI,
                'iva' => $totalIV2,
                'ieps' => $totalIEPS2,
                'total' => $totalNeto,
            ],
            'aceites' => [
                'total' => $totalAceites,
                'sin_iva' => $aceitesSinIva,
                'iva' => $aceitesIva,
            ],
            'total' => [
                'importe_sin_iva' => $totalDiaISI,
                'iva' => $totalDiaIVA,
                'ieps' => $totalIEPS2,
                'total' => $totalDia,
            ],
        ];
    }
}
