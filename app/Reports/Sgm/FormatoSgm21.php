<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\CumplimientoObjetivosRevision;
use App\Models\Sgm\CumplimientoObjetivosRevisionDetalle;

class FormatoSgm21
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }


        $registro = CumplimientoObjetivosRevision::query()
            ->from('sgm_cumplimiento_objetivos_revision')
            ->where('id_estacion', $idestacion)
            ->where('year', $year)
            ->where('estado', 1)
            ->first();

        if (!$registro) {
            return '';
        }

        $idRegistro = (int) $registro->id;

        $realizadoPor = $this->usuario(
            (int) $registro->realizadopor
        );

        $fechaRaw = $registro->getRawOriginal('fecha');

        $contenido = '';

        /*
         * Encabezado Fo.SGM.021
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Cumplimiento de objetivos y revisión por la dirección</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.021';
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
         * Datos generales
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Fecha:</b></td>';
        $contenido .= '<td>';
        $contenido .= $this->fechaSegura($fechaRaw);
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Hora:</b></td>';
        $contenido .= '<td>';
        $contenido .= $this->formatoHora($registro->hora);
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Lugar:</b></td>';
        $contenido .= '<td>';
        $contenido .= $this->e($registro->lugar);
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Responsable de la medición:</b></td>';
        $contenido .= '<td>';
        $contenido .= $this->e($registro->responsable);
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '</tbody>';
        $contenido .= '</table>';


        $detalles = CumplimientoObjetivosRevisionDetalle::query()
            ->from('sgm_cumplimiento_objetivos_revision_detalle')
            ->where('id_cumplimiento', $idRegistro)
            ->get();

        foreach ($detalles as $detalle) {
            if (
                $detalle->categoria ===
                'Indicador: Satisfacción del cliente'
            ) {
                $meta = 'Meta: disminuir 30% de reclamaciones contra el año inmediato anterior';
            } else {
                $meta = 'Meta: 100%';
            }

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= '<b>' . $this->e($detalle->categoria) . '</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle">';
            $contenido .= '<b>' . $this->e($meta) . '</b>';
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= '<b>Resultado</b>';
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($detalle->resultado1);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle">';
            $contenido .= '<b>Comentarios y observaciones:</b>';
            $contenido .= '</td>';

            $contenido .= '<td colspan="2" valign="middle">';
            $contenido .= $this->e($detalle->resultado2);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= '<b>Acciones a tomar para mejorar o mantener el resultado:</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($detalle->resultado3);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= '<b>Responsable de realizar las acciones a tomar para mejorar o mantener los resultados:</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($detalle->resultado4);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= '<b>Recursos necesarios para ejecutar las acciones a tomar para mejorar o mantener los resultados:</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($detalle->resultado5);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';
        }

        $contenido .= '<hr><br>';

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

    private function formatoHora(mixed $hora): string
    {
        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('H:i');
        }

        $hora = trim((string) ($hora ?? ''));

        if ($hora === '') {
            return '';
        }

        $timestamp = strtotime($hora);

        if ($timestamp === false) {
            return $this->e($hora);
        }

        return date('H:i', $timestamp);
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
