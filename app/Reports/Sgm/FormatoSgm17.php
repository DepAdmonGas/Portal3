<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\BitacoraCalibracionEquipo;
use App\Models\Sgm\BitacoraCalibracionEquipoDetalle;

class FormatoSgm17
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

        $programas = ProgramaAnualCalibracionVerificacion::query()
            ->from('sgm_programa_anual_calibracion_verificacion')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->where('estado', 1)
            ->get([
                'id',
            ]);

        foreach ($programas as $programa) {
            $idRegistro = (int) $programa->id;

            /*
             * Bitácora de calibración
             */
            $bitacora = BitacoraCalibracionEquipo::query()
                ->from('sgm_bitacora_calibracion_equipo')
                ->where('id_programa', $idRegistro)
                ->first();

            if (!$bitacora) {
                continue;
            }

            $fechaRaw = $bitacora->getRawOriginal('fecha');

            $realizadoPor = $this->usuario(
                (int) $bitacora->realizadopor
            );

            /*
             * Encabezado Fo.SGM.017
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Bitácora la para la calibración de equipos</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.017';
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
             * Datos generales de calibración
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
            $contenido .= '<td valign="middle"><b>Hora:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->formatoHora($bitacora->hora);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Nombre del equipo a calibrar:</b></td>';
            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($bitacora->nombre_equipo);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Marca:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->marca);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Capacidad:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->capacidad);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Producto que almacena:</b></td>';
            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($bitacora->almacena);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Nombre del laboratorio o unidad de verificación encargada de la calibración:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->nombre_laboratorio);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>No de acreditación o aprobación:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->no_acreditacion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Método utilizado para la calibración:</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->metodo_calibracion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * Descripción de patrones utilizados
             */
            $contenido .= '<h5>Descripción de patrones utilizados</h5>';

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Nombre del patrón</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->nombre_patron);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Marca y modelo y serie</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->marca_modelo_serie);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Resolución</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->resolucion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Incertidumbre</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->incertidumbre);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Vigencia de su certificado de calibración</b></td>';
            $contenido .= '<td>';
            $contenido .= $this->e($bitacora->vigencia_certificado);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * Equipos / instrumentos calibrados
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<thead>';

            $contenido .= '<tr>';
            $contenido .= '<th>Equipo o Instrumento</th>';
            $contenido .= '<th>Identificacion</th>';
            $contenido .= '<th>Resultado</th>';
            $contenido .= '</tr>';

            $contenido .= '</thead>';
            $contenido .= '<tbody>';

            $contenido .= $this->detalleEquipos($idRegistro);

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            $contenido .= '<hr><br>';
        }

        return $contenido;
    }

    private function detalleEquipos(int $idPrograma): string
    {
        $contenido = '';

        /*
         * Código original:
         *
         * sgm_bitacora_calibracion_equipo_detalle
         * INNER JOIN sgm_inventario_equipo
         */
        $equipos = BitacoraCalibracionEquipoDetalle::query()
            ->from('sgm_bitacora_calibracion_equipo_detalle')
            ->join(
                'sgm_inventario_equipo',
                'sgm_bitacora_calibracion_equipo_detalle.id_equipo',
                '=',
                'sgm_inventario_equipo.id'
            )
            ->where(
                'sgm_bitacora_calibracion_equipo_detalle.id_programa',
                $idPrograma
            )
            ->get([
                'sgm_bitacora_calibracion_equipo_detalle.id',
                'sgm_bitacora_calibracion_equipo_detalle.id_equipo',
                'sgm_bitacora_calibracion_equipo_detalle.resultado',
                'sgm_inventario_equipo.nombre',
                'sgm_inventario_equipo.identificacion',
            ]);

        if ($equipos->isEmpty()) {
            return '<tr><td colspan="3" align="center">No se encontró información</td></tr>';
        }

        foreach ($equipos as $equipo) {
            $contenido .= '<tr>';

            $contenido .= '<td>';
            $contenido .= $this->e($equipo->nombre);
            $contenido .= '</td>';

            $contenido .= '<td>';
            $contenido .= $this->e($equipo->identificacion);
            $contenido .= '</td>';

            $contenido .= '<td>';
            $contenido .= $this->e($equipo->resultado);
            $contenido .= '</td>';

            $contenido .= '</tr>';
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
