<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\HallazgoAuditoria;

class FormatoSgm19
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

        /*
         * Código original:
         *
         * SELECT id
         * FROM sgm_auditoria
         * WHERE id_estacion = ?
         * AND year = ?
         * AND estado = 1
         */
        $auditorias = Auditoria::query()
            ->from('sgm_auditoria')
            ->where('id_estacion', $idestacion)
            ->where('year', $year)
            ->where('estado', 1)
            ->get([
                'id',
            ]);

        foreach ($auditorias as $auditoria) {
            $idAuditoria = (int) $auditoria->id;

            /*
             * Hallazgos de auditoría
             */
            $hallazgos = HallazgoAuditoria::query()
                ->from('sgm_hallazgo_auditoria')
                ->where('id_auditoria', $idAuditoria)
                ->get();

            foreach ($hallazgos as $hallazgo) {
                $realizadoPor = $this->usuario(
                    (int) $hallazgo->realizadopor
                );

                $fechaRaw = $hallazgo->getRawOriginal('fecha');

                /*
                 * Encabezado Fo.SGM.019
                 */
                $contenido .= '<table class="table table-sm table-bordered align-middle">';
                $contenido .= '<tbody>';

                $contenido .= '<tr>';

                $contenido .= '<td rowspan="2" align="center" valign="middle">';
                $contenido .= $this->e($estacion->razonsocial);
                $contenido .= '</td>';

                $contenido .= '<td rowspan="2" align="center" valign="middle">';
                $contenido .= '<b>Reporte de Hallazgos de Auditoria</b>';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Fo.SGM.019';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Realizado por:<br>';
                $contenido .= $this->e($realizadoPor['nombre']);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Revisado por:<br>Eduardo Galicia Flores';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Autorizado por:<br>';
                $contenido .= $this->e($estacion->apoderado_legal);
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $contenido .= '</tbody>';
                $contenido .= '</table>';

                /*
                 * Datos del hallazgo
                 */
                $contenido .= '<table class="table table-sm table-bordered align-middle">';
                $contenido .= '<tbody>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>Fecha:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->fechaSegura($fechaRaw);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>No de Hallazgo:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->e($hallazgo->no_hallazgo);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>Descripción del criterio:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->e($hallazgo->descripcion_criterio);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>Clasificación del Hallazgo:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->e($hallazgo->clasificacion_hallazgo);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>Descripción del Hallazgo:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->e($hallazgo->descripcion_hallazgo);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle"><b>Evidencia:</b></td>';
                $contenido .= '<td>';
                $contenido .= $this->evidencia($hallazgo->evidencia);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '</tbody>';
                $contenido .= '</table>';

                $contenido .= '<hr><br>';
            }
        }

        return $contenido;
    }

    private function usuario(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            return [
                'nombre' => '',
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $idUsuario)
            ->first();

        return [
            'nombre' => $usuario?->nombre ?? '',
        ];
    }

    private function evidencia(mixed $archivo): string
    {
        $archivo = trim((string) ($archivo ?? ''));

        if ($archivo === '') {
            return '';
        }

        $url = dirname(__DIR__, 2) . '/public/uploads/archivos/sgm/' . $archivo;

        return '<a href="'
            . $this->e($url)
            . '">'
            . $this->e($archivo)
            . '</a>';
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
