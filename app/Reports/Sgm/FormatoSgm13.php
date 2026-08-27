<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\OrdenServicio;
use App\Models\Sgm\EvaluacionProveedor;

class FormatoSgm13
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

        /*
         * El formato original parte de las órdenes de servicio
         * correspondientes a la estación y al año solicitado.
         */
        $ordenes = OrdenServicio::query()
            ->from('sgm_orden_servicio')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->get();

        foreach ($ordenes as $orden) {
            /*
             * En el código original la evaluación se obtiene por
             * id_orden_servicio.
             *
             * Evitamos generar el formato cuando la orden todavía
             * no cuenta con una evaluación.
             */
            $evaluacion = EvaluacionProveedor::query()
                ->from('sgm_evaluacion_proveedores')
                ->where('id_orden_servicio', $orden->id)
                ->first();

            if (!$evaluacion) {
                continue;
            }

            $realizadoPor = $this->usuario((int) $orden->realizadopor);
            $personalEvaluacion = $this->usuario(
                (int) $evaluacion->id_personal_evaluacion
            );

            $fechaRaw = $evaluacion->getRawOriginal('fecha');

            $respuesta1 = (int) $evaluacion->respuesta_1;
            $respuesta2 = (int) $evaluacion->respuesta_2;
            $respuesta3 = (int) $evaluacion->respuesta_3;
            $respuesta4 = (int) $evaluacion->respuesta_4;
            $respuesta5 = (int) $evaluacion->respuesta_5;

            $sumaRespuestas = $respuesta1
                + $respuesta2
                + $respuesta3
                + $respuesta4
                + $respuesta5;

            $resultado = ($sumaRespuestas / 5) * 100;

            /*
             * Encabezado Fo.SGM.013
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Evaluación de proveedores</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.013';
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
             * Información del servicio y proveedor
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Trabajo realizado o producto adquirido:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($orden->descripcion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Fecha de ejecución del servicio:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->fechaSegura($fechaRaw);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Hora de inicio del servicio:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->formatoHora($evaluacion->hora_inicio);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Hora de culminación del servicio:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->formatoHora($evaluacion->hora_termino);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Nombre del proveedor o prestador de servicio:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($evaluacion->nombre_proveedor);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>No de acreditación o aprobación:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($evaluacion->no_acreditacion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            /*
             * Evaluación
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<thead>';

            $contenido .= '<tr>';
            $contenido .= '<th align="center">No</th>';
            $contenido .= '<th>Criterio de evaluación</th>';
            $contenido .= '<th align="center">Resultado</th>';
            $contenido .= '</tr>';

            $contenido .= '</thead>';
            $contenido .= '<tbody>';

            $contenido .= $this->filaRespuesta(
                1,
                '¿El proveedor cumplió con el trabajo o producto solicitado?',
                $respuesta1
            );

            $contenido .= $this->filaRespuesta(
                2,
                '¿El proveedor cumplió con los tiempos establecidos?',
                $respuesta2
            );

            $contenido .= $this->filaRespuesta(
                3,
                '¿El proveedor cumplió con las especificaciones solicitadas?',
                $respuesta3
            );

            $contenido .= $this->filaRespuesta(
                4,
                '¿El proveedor presentó la documentación correspondiente?',
                $respuesta4
            );

            $contenido .= $this->filaRespuesta(
                5,
                '¿El servicio o producto recibido fue satisfactorio?',
                $respuesta5
            );

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            /*
             * Resultado global
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Resultado de la evaluación:</b></td>';
            $contenido .= '<td align="center">';
            $contenido .= $this->e($this->formatoPorcentaje($resultado));
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Observaciones:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($evaluacion->observaciones);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Personal que realizó la evaluación:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($personalEvaluacion['nombre']);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
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

    private function filaRespuesta(
        int $numero,
        string $pregunta,
        int $respuesta
    ): string {
        $contenido = '';

        $contenido .= '<tr>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= $numero;
        $contenido .= '</td>';

        $contenido .= '<td valign="middle">';
        $contenido .= $this->e($pregunta);
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= $respuesta === 1 ? 'SI' : 'NO';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        return $contenido;
    }

    private function formatoPorcentaje(float $resultado): string
    {
        if (floor($resultado) === $resultado) {
            return number_format($resultado, 0) . ' %';
        }

        return number_format($resultado, 2) . ' %';
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
