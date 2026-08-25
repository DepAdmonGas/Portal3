<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\InventarioEquipo;
use App\Models\Sgm\InventarioEquipoManual;

class FormatoSgm11
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
         * Encabezado Fo.SGM.011
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Inventario de equipo</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.011';
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
         * Inventario de equipo
         *
         * Código original:
         *
         * SELECT *
         * FROM sgm_inventario_equipo
         * WHERE id_estacion = ?
         * AND estado < 2
         * ORDER BY nombre DESC
         */
        $equipos = InventarioEquipo::query()
            ->from('sgm_inventario_equipo')
            ->where('id_estacion', $idestacion)
            ->where('estado', '<', 2)
            ->orderBy('nombre', 'desc')
            ->get();

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';

        $contenido .= '<tr>';
        $contenido .= '<th align="center" valign="middle">#</th>';
        $contenido .= '<th align="center" valign="middle">Nombre del equipo de medición</th>';
        $contenido .= '<th align="center" valign="middle">Identificación</th>';
        $contenido .= '<th align="center" valign="middle">Función que desempeña dentro de la ES</th>';
        $contenido .= '<th align="center" valign="middle">Fecha de instalación</th>';
        $contenido .= '<th align="center" valign="middle">Manuales, garantías o información documental del equipo</th>';
        $contenido .= '</tr>';

        $contenido .= '</thead>';
        $contenido .= '<tbody>';

        if ($equipos->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="6" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($equipos as $equipo) {
                $fechaInstalacionRaw = $equipo->fecha_instalacion->format('Y-m-d');

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $numero;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($equipo->nombre);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($equipo->identificacion);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($equipo->funcion);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->fechaInstalacion($fechaInstalacionRaw);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->manuales((int) $equipo->id);
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $numero++;
            }
        }

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

    private function manuales(int $idEquipo): string
    {
        $manuales = InventarioEquipoManual::query()
            ->from('sgm_inventario_equipo_manual')
            ->where('id_equipo', $idEquipo)
            ->get();

        if ($manuales->isEmpty()) {
            return '';
        }

        $archivos = [];

        foreach ($manuales as $manual) {
            if (empty($manual->archivo)) {
                continue;
            }

            $url = $_ENV['APP_URL']  . '/public/uploads/archivos/manuales/' . $manual->archivo;

            $archivos[] = '<a href="'
                . $this->e($url)
                . '">'
                . $this->e($manual->archivo)
                . '</a>';
        }

        return implode(' ', $archivos);
    }

    private function fechaInstalacion(mixed $fecha): string
    {
        $fecha = trim((string) ($fecha ?? ''));

        if (
            $fecha === ''
            || $fecha === '0000-00-00'
            || $fecha === '0000-00-00 00:00:00'
        ) {
            return '';
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
            return '';
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
