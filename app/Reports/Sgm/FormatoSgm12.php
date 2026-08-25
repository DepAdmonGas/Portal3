<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\OrdenServicio;

class FormatoSgm12
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

        $registros = OrdenServicio::query()
            ->from('sgm_orden_servicio')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->get();

        foreach ($registros as $registro) {
            $solicitante = $this->usuario((int) $registro->id_solicitante);
            $realizadoPor = $this->usuario((int) $registro->realizadopor);

            $fechaRaw = $registro->getRawOriginal('fecha');

            /*
             * Encabezado Fo.SGM.012
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Orden de servicio</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.012';
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
             * Datos de la orden de servicio
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
            $contenido .= '<td><b>Nombre del solicitante:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($solicitante['nombre']);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Puesto:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($solicitante['puesto']);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Razón Social:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>RFC:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($estacion->rfc);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Dirección:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($estacion->direccioncompleta);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * Descripción detallada
             */
            $contenido .= '<div class="mt-2">';
            $contenido .= '<b>Descripción detallada del servicio equipo que requiere:</b>';
            $contenido .= '</div>';


            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tr>';
            $contenido .= '<td>';
            $contenido .= $this->e($registro->descripcion);
            $contenido .= '</td>';
            $contenido .= '</tr>';
            $contenido .= '</table>';

            /*
             * Justificación
             */
            $contenido .= '<div class="mt-2">';
            $contenido .= '<b>Justificación del servicio que requiere:</b>';
            $contenido .= '</div>';

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tr>';
            $contenido .= '<td>';
            $contenido .= $this->e($registro->justificacion);
            $contenido .= '</td>';
            $contenido .= '</tr>';
            $contenido .= '</table>';

            $contenido .= '<hr><br>';
        }

        return $contenido;
    }

    private function usuario(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            return [
                'nombre' => '',
                'puesto' => '',
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->leftJoin(
                'tb_puestos',
                'tb_usuarios.id_puesto',
                '=',
                'tb_puestos.id'
            )
            ->where('tb_usuarios.id', $idUsuario)
            ->select([
                'tb_usuarios.nombre',
                'tb_puestos.tipo_puesto',
            ])
            ->first();

        if (!$usuario) {
            return [
                'nombre' => '',
                'puesto' => '',
            ];
        }

        return [
            'nombre' => $usuario->nombre ?? '',
            'puesto' => $usuario->tipo_puesto ?? '',
        ];
    }

    private function formatoHora($hora): string
    {
        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('H:i');
        }

        if (empty($hora)) {
            return '';
        }

        $timestamp = strtotime((string) $hora);

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
