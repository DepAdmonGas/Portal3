<?php

namespace App\Services;

use App\Models\Sasisopa\CapacitacionExterna;
use App\Models\Sasisopa\CapacitacionExternaPersonal;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sasisopa\CursoModulo;
use App\Models\Usuario;

class CapacitacionService
{
    private const CALIFICACION_APROBATORIA = 60;

    private const PUESTOS_CAPACITACION = [6, 7, 9, 10, 11];

    public static function getResumen(int $estacionId, int $year): array
    {
        $data = [
            'year' => 'Año ' . $year,
            'modulos' => [],
            'totales' => [],
            'externa' => [],
        ];

        $modulos = CursoModulo::all();

        $totalCursos = 0;
        $totalAcreditados = 0;
        $totalNoAcreditados = 0;

        foreach ($modulos as $modulo) {

            $estadisticas = self::getEstadisticasModulo(
                estacionId: $estacionId,
                year: $year,
                moduloId: $modulo->id
            );

            $porcentajeAcreditado = self::calcularPorcentaje(
            $estadisticas['acreditados'],
            $estadisticas['total']
            );

        $data['modulos'][] = [
            'modulo' => "{$modulo->num_modulo}.- {$modulo->titulo}",
            'total' => $estadisticas['total'],
            'acreditado' => $porcentajeAcreditado,
            'no_acreditado' => $estadisticas['total'] > 0
                ? 100 - $porcentajeAcreditado
                : 0,
        ];

            $totalCursos += $estadisticas['total'];
            $totalAcreditados += $estadisticas['acreditados'];
            $totalNoAcreditados += $estadisticas['no_acreditados'];
        }

        $porcentajeAcreditado = self::calcularPorcentaje(
            $totalAcreditados,
            $totalCursos
        );

        $data['totales'] = [
            'total_cursos' => $totalCursos,
            'acreditado' => $porcentajeAcreditado,
            'no_acreditado' => $totalCursos > 0
                ? 100 - $porcentajeAcreditado
                : 0,
        ];

        $data['externa'] = self::getCapacitacionExterna(
            estacionId: $estacionId,
            year: $year
        );

        return $data;
    }

    private static function getEstadisticasModulo(
        int $estacionId,
        int $year,
        int $moduloId
    ): array {
        $cursos = CursoCalendario::query()
            ->join(
                'tb_cursos_temas as t',
                'tb_cursos_calendario.id_tema',
                '=',
                't.id'
            )
            ->where('tb_cursos_calendario.id_estacion', $estacionId)
            ->where('t.id_modulo', $moduloId)
            ->whereYear('tb_cursos_calendario.fecha_programada', $year)
            ->select([
                'tb_cursos_calendario.id',
                'tb_cursos_calendario.resultado',
            ])
            ->get();

        $total = $cursos->count();

        $acreditados = $cursos
            ->where('resultado', '>=', self::CALIFICACION_APROBATORIA)
            ->count();

        return [
            'total' => $total,
            'acreditados' => $acreditados,
            'no_acreditados' => $total - $acreditados,
        ];
    }

    private static function getCapacitacionExterna(
        int $estacionId,
        int $year
    ): array {
        $totalPersonal = Usuario::activo()
            ->where('id_gas', $estacionId)
            ->whereIn('id_puesto', self::PUESTOS_CAPACITACION)
            ->count();

        $capacitaciones = CapacitacionExterna::query()
            ->where('id_estacion', $estacionId)
            ->whereYear('fechacreacion', $year)
            ->get(['id']);

        $totalCapacitaciones = $capacitaciones->count();

        $capacitacionIds = $capacitaciones->pluck('id');

        $totalPersonalCapacitado = $capacitacionIds->isNotEmpty()
        ? CapacitacionExternaPersonal::query()
            ->whereIn('id_capacitacion', $capacitacionIds)
            ->distinct('id_empleado')
            ->count('id_empleado')
        : 0;

        $promedioPersonal = $totalCapacitaciones > 0
            ? (int) ceil($totalPersonalCapacitado / $totalCapacitaciones)
            : 0;

        return [
            'total_personal' => $totalPersonal,
            'total_capacitaciones' => $totalCapacitaciones,
            'personal_capacitado' => $promedioPersonal,
            'porcentaje' => self::calcularPorcentaje(
                $promedioPersonal,
                $totalPersonal
            ),
        ];
    }

    private static function calcularPorcentaje(
        int $cantidad,
        int $total
    ): int {
        if ($total <= 0) {
            return 0;
        }

        return (int) ceil(($cantidad / $total) * 100);
    }
}