<?php
namespace App\Services;

use App\Models\Sasisopa\CursoModulo;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sasisopa\CursoTema;
use App\Models\Sasisopa\CapacitacionExterna;
use App\Models\Sasisopa\CapacitacionExternaPersonal;
use App\Models\Usuario;

class CapacitacionService{

    public static function getResumen($estacionId, $year)
    {
            $data = [
                'year' => 'Año '.$year,
                'modulos' => [],
                'totales' => [],
                'externa' => []
            ];

            $modulos = CursoModulo::all();

            $totalCursos = 0;
            $netoAcreditado = 0;
            $netoNoAcreditado = 0;

            foreach ($modulos as $modulo) {

                $cursos = CursoCalendario::query()
                    ->join('tb_cursos_temas as t', 'tb_cursos_calendario.id_tema', '=', 't.id')
                    ->where('tb_cursos_calendario.id_estacion', $estacionId)
                    ->where('t.id_modulo', $modulo->id)
                    ->whereYear('tb_cursos_calendario.fecha_programada', $year)
                    ->select('tb_cursos_calendario.*')
                    ->get();

                $total = $cursos->count();

                $acreditado = $cursos->where('resultado', '>=', 60)->count();
                $noAcreditado = $total - $acreditado;

                $data['modulos'][] = [
                    'modulo' => $modulo->num_modulo . '.- ' . $modulo->titulo,
                    'total' => $total,
                    'acreditado' => $total ? ceil(($acreditado / $total) * 100) : 0,
                    'no_acreditado' => $total ? ceil(($noAcreditado / $total) * 100) : 0
                ];

                $totalCursos += $total;
                $netoAcreditado += $acreditado;
                $netoNoAcreditado += $noAcreditado;
            }

            // Totales interna
            $data['totales'] = [
                'total_cursos' => $totalCursos,
                'acreditado' => $totalCursos ? ceil(($netoAcreditado / $totalCursos) * 100) : 0,
                'no_acreditado' => $totalCursos ? ceil(($netoNoAcreditado / $totalCursos) * 100) : 0
            ];

            // EXTERNA
            $totalPersonal = Usuario::activo()
                ->where('id_gas', $estacionId)
                ->whereIn('id_puesto', [6,7,9,10,11])
                ->count();

            $capacitaciones = CapacitacionExterna::where('id_estacion', $estacionId)
                ->whereYear('fechacreacion', $year)
                ->get();

            $totalCapacitaciones = $capacitaciones->count();

            $totalPersonalCapacitado = 0;

            foreach ($capacitaciones as $cap) {
                $totalPersonalCapacitado += CapacitacionExternaPersonal::where('id_capacitacion', $cap->id)->count();
            }

            $promedioPersonal = $totalCapacitaciones
                ? ceil($totalPersonalCapacitado / $totalCapacitaciones)
                : 0;

            $porcentaje = $totalPersonal
                ? ceil(($promedioPersonal / $totalPersonal) * 100)
                : 0;

            $data['externa'] = [
                'total_personal' => $totalPersonal,
                'total_capacitaciones' => $totalCapacitaciones,
                'personal_capacitado' => $promedioPersonal,
                'porcentaje' => $porcentaje
            ];

            return $data;
    }
}