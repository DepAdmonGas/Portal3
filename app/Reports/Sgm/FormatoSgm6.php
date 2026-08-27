<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Models\Sgm\Autorizado;

class FormatoSgm6
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
         * Encabezado Fo.SGM.006
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Requisitos legales del SGM</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.006';
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
        $contenido .= '</table>';

        /*
         * Tabla de requisitos legales
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';
        $contenido .= '<tr>';
        $contenido .= '<th>No</th>';
        $contenido .= '<th>Nombre del permiso</th>';
        $contenido .= '<th>Periodicidad</th>';
        $contenido .= '<th>Fecha emisión</th>';
        $contenido .= '<th>Fecha vencimiento</th>';
        $contenido .= '<th>Fundamento legal</th>';
        $contenido .= '</tr>';
        $contenido .= '</thead>';

        $contenido .= '<tbody>';
        $contenido .= $this->tablaRequisitosLegales($idestacion);
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

    private function tablaRequisitosLegales(int $idestacion): string
    {
        $contenido = '';

        /*
         * Modelo real:
         * RequisitosLegalesCalendario
         *
         * Tabla:
         * rl_requisitos_legales_calendario
         *
         * Relación:
         * requisito() -> rl_requisitos_legales_lista
         */
        $requisitos = RequisitosLegalesCalendario::query()
            ->with('requisito')
            ->where('id_estacion', $idestacion)
            ->where('estado', 1)
            ->where('id_requisito_legal', '<>', 0)
            ->whereHas('requisito', function ($query) {
                $query->where('sgm', 1);
            })
            ->orderBy('id_requisito_legal')
            ->get();

        if ($requisitos->isEmpty()) {
            return '<tr>
                <td colspan="6" align="center">
                    No se encontró información para mostrar
                </td>
            </tr>';
        }

        foreach ($requisitos as $item) {
            $requisito = $item->requisito;

            if (!$requisito) {
                continue;
            }

            $ultima = $this->ultimaActualizacion((int) $item->id);

            $contenido .= '<tr>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($requisito->id);
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= '<b>' . $this->e($requisito->permiso) . '</b>';
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($item->vigencia);
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $ultima['fecha_emision'];
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $ultima['fecha_vencimiento'];
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($requisito->fundamento);
            $contenido .= '</td>';

            $contenido .= '</tr>';
        }

        return $contenido;
    }

    private function ultimaActualizacion(int $idCalendario): array
    {
        $matriz = RequisitosLegalesMatriz::query()
            ->where('idcalendario', $idCalendario)
            ->latest('id')
            ->first();

        if (!$matriz) {
            return [
                'fecha_emision' => 'S/I',
                'fecha_vencimiento' => 'S/I',
            ];
        }

        /*
         * Se leen los valores originales para evitar que
         * 0000-00-00 sea convertido por Carbon.
         */
        $fechaEmision = $matriz->getRawOriginal('fecha_emision');
        $fechaVencimiento = $matriz->getRawOriginal('fecha_vencimiento');

        return [
            'fecha_emision' => $this->fechaSegura($fechaEmision),
            'fecha_vencimiento' => $this->fechaSegura($fechaVencimiento),
        ];
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

        $mes = $meses[$partes[1]] ?? $partes[1];

        return $partes[2]
            . ' de '
            . $mes
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
