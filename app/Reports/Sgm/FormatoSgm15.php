<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\InventarioEquipo;

class FormatoSgm15
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $realizadoPor = $this->realizadoPor($idestacion);

        $contenido = '';

        /*
         * Encabezado SGM.Fo.015
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Programa anual de verificación de equipos de medición</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'SGM.Fo.015';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Realizado por:<br>' . $this->e($realizadoPor);
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Revisado por:<br>Eduardo Galicia Flores';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Autorizado por:<br>' . $this->e($estacion->apoderado_legal);
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        /*
         * Equipo sometido a verificación
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= $this->contenidoTabla(
            $idestacion,
            $year,
            'Equipo sometido a verificación'
        );

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        $contenido .= '<hr><br>';

        return $contenido;
    }

    private function realizadoPor(int $idestacion): string
    {
        return Autorizado::query()
            ->join(
                'tb_usuarios',
                'tb_usuarios.id',
                '=',
                'sgm_autorizado.id_usuario'
            )
            ->where('tb_usuarios.id_gas', $idestacion)
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.nombre') ?? 'S/I';
    }

    private function contenidoTabla(
        int $idestacion,
        int $year,
        string $categoria
    ): string {
        $contenido = '';

        $registros = ProgramaAnualCalibracionVerificacion::query()
            ->from('sgm_programa_anual_calibracion_verificacion')
            ->join(
                'sgm_patrones_instrumentos',
                'sgm_programa_anual_calibracion_verificacion.id_equipo',
                '=',
                'sgm_patrones_instrumentos.id'
            )
            ->where(
                'sgm_programa_anual_calibracion_verificacion.id_estacion',
                $idestacion
            )
            ->whereYear(
                'sgm_programa_anual_calibracion_verificacion.fecha',
                $year
            )
            ->where(
                'sgm_patrones_instrumentos.categoria',
                $categoria
            )
            ->orderBy(
                'sgm_programa_anual_calibracion_verificacion.fecha',
                'asc'
            )
            ->get([
                'sgm_programa_anual_calibracion_verificacion.id',
                'sgm_programa_anual_calibracion_verificacion.fecha',
                'sgm_programa_anual_calibracion_verificacion.id_verificar',
                'sgm_patrones_instrumentos.nombre',
                'sgm_patrones_instrumentos.periodicidad',
                'sgm_patrones_instrumentos.categoria',
            ]);

        $contenido .= '<tr>';
        $contenido .= '<td><b>' . $this->e($categoria) . '</b></td>';
        $contenido .= '<td><b>Periodicidad</b></td>';
        $contenido .= '<td><b>Fechas programadas</b></td>';
        $contenido .= '</tr>';

        if ($registros->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            return $contenido;
        }

        foreach ($registros as $registro) {
            $detalleEquipo = '';

            if ((int) $registro->id_verificar !== 0) {
                $identificacion = $this->detalleEquipo(
                    (int) $registro->id_verificar
                );

                if ($identificacion !== '') {
                    $detalleEquipo = ' ' . $identificacion;
                }
            }

            $fechaRaw = $registro->getRawOriginal('fecha');

            $contenido .= '<tr>';

            $contenido .= '<td>';
            $contenido .= $this->e(
                $registro->nombre . $detalleEquipo
            );
            $contenido .= '</td>';

            $contenido .= '<td>';
            $contenido .= $this->e($registro->periodicidad);
            $contenido .= '</td>';

            $contenido .= '<td>';
            $contenido .= $this->fechaSegura($fechaRaw);
            $contenido .= '</td>';

            $contenido .= '</tr>';
        }

        return $contenido;
    }

    private function detalleEquipo(int $idEquipo): string
    {
        if ($idEquipo <= 0) {
            return '';
        }

        return (string) (
            InventarioEquipo::query()
            ->from('sgm_inventario_equipo')
            ->where('id', $idEquipo)
            ->value('identificacion') ?? ''
        );
    }

    private function fechaSegura(mixed $fecha): string
    {
        $fecha = trim((string) ($fecha ?? ''));

        if (
            $fecha === ''
            || $fecha === '0000-00-00'
            || $fecha === '0000-00-00 00:00:00'
        ) {
            return 'S/I';
        }

        return $this->formatoFecha($fecha);
    }

    private function formatoFecha(string $fecha): string
    {
        $fecha = substr($fecha, 0, 10);

        $partes = explode('-', $fecha);

        if (
            count($partes) !== 3
            || !checkdate(
                (int) $partes[1],
                (int) $partes[2],
                (int) $partes[0]
            )
        ) {
            return 'S/I';
        }

        $meses = [
            '01' => 'Enero',
            '02' => 'Febrero',
            '03' => 'Marzo',
            '04' => 'Abril',
            '05' => 'Mayo',
            '06' => 'Junio',
            '07' => 'Julio',
            '08' => 'Agosto',
            '09' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre',
        ];

        return $partes[2]
            . ' de '
            . ($meses[$partes[1]] ?? $partes[1])
            . ' del '
            . $partes[0];
    }

    private function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) ($valor ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
