<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\BitacoraVerificacionSensores;
use App\Models\Sgm\BitacoraVerificacionResultado;

class FormatoSgm16
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
            ->get();

        foreach ($programas as $programa) {
            $idRegistro = (int) $programa->id;

            /*
             * Bitácora de verificación de sensores
             */
            $bitacora = BitacoraVerificacionSensores::query()
                ->from('sgm_bitacora_verificacion_sensores')
                ->where('id_programa', $idRegistro)
                ->first();

            if ($bitacora) {
                $fechaRaw = $bitacora->getRawOriginal('fecha');

                $fecha = $this->fechaSegura($fechaRaw);
                $hora = $this->formatoHora($bitacora->hora);
                $noTanque = $bitacora->no_tanque ?? '';
                $marca = $bitacora->marca ?? '';
                $capacidad = $bitacora->capacidad ?? '';
                $producto = $bitacora->producto ?? '';
                $internoExterno = $bitacora->interno_externo ?? '';
                $verificacionMovimiento = $bitacora->verificacion_movimiento ?? '';
                $metodoNivel = $bitacora->metodo_nivel ?? '';

                $realizadoPor = $this->usuario(
                    (int) $bitacora->realizadopor
                );
            } else {
                $fecha = '';
                $hora = '';
                $noTanque = '';
                $marca = '';
                $capacidad = '';
                $producto = '';
                $internoExterno = '';
                $verificacionMovimiento = '';
                $metodoNivel = '';
                $realizadoPor = [
                    'nombre' => '',
                ];
            }

            /*
             * Encabezado Fo.SGM.016
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Bitácora para la verificación de equipos de medicion</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.016';
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
             * Datos generales de verificación
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Fecha:</b></td>';
            $contenido .= '<td valign="middle">' . $fecha . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Hora:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($hora) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td><b>Verificacion de sensores de nivel y temperatura</b></td>';
            $contenido .= '<td><b>Resultado</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>No de tanque:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($noTanque) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Marca:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($marca) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Capacidad:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($capacidad) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Producto que almacena:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($producto) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>La verificación es realizada por personal Interno o Externo, ( en caso de ser externo colocar nombre de la empresa y datos relevantes):</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($internoExterno) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Al momento de iniciar la calibración se asegura que el producto se encuentre sin movimiento:</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($verificacionMovimiento) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td valign="middle"><b>Método para determinar el nivel liquido dentro del tanque (Inmersión o medida seca):</b></td>';
            $contenido .= '<td valign="middle">' . $this->e($metodoNivel) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            /*
             * Resultados por categoría
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';

            $contenido .= $this->contenidoTabla16(
                $idRegistro,
                '1. Aspecto a verificar en los patrones de referencia'
            );

            $contenido .= $this->contenidoTabla16(
                $idRegistro,
                '2. Sistema de nivel automático (tirilla del Sistema de Control de Inventarios)'
            );

            $contenido .= $this->contenidoTabla16(
                $idRegistro,
                '3. Medición de la cinta petrolera (en mm) y termómetro (en °C)'
            );

            $contenido .= $this->contenidoTabla16(
                $idRegistro,
                '4. Resultado: Diferencia entre ambas mediciones'
            );

            $contenido .= '</table>';

            $contenido .= '<br>';

            /*
             * Notas originales
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tr>';
            $contenido .= '<td>';
            $contenido .= '<b>Nota 1:</b> Referente al nivel puede existir una variación de +/- 3 mm, sin embargo, para aplicaciones fiscales o de transferencia de custodia, los equipos deben cumplir con un EMP de ± 4 mm, en todo el intervalo de medición.<br>';
            $contenido .= '<b>Nota 2:</b> referente a la temperatura puede existir una variación igual o menor de 0.5 °C';
            $contenido .= '</td>';
            $contenido .= '</tr>';
            $contenido .= '</table>';

            $contenido .= '<hr><br>';
        }

        return $contenido;
    }

    private function contenidoTabla16(
        int $idPrograma,
        string $categoria
    ): string {
        $contenido = '';

        /*
         * Código original:
         *
         * sgm_bitacora_verificacion_resultado
         * INNER JOIN sgm_bitacora_verificacion_lista
         */
        $resultados = BitacoraVerificacionResultado::query()
            ->from('sgm_bitacora_verificacion_resultado')
            ->join(
                'sgm_bitacora_verificacion_lista',
                'sgm_bitacora_verificacion_resultado.id_lista',
                '=',
                'sgm_bitacora_verificacion_lista.id'
            )
            ->where(
                'sgm_bitacora_verificacion_resultado.id_programa',
                $idPrograma
            )
            ->where(
                'sgm_bitacora_verificacion_lista.categoria',
                $categoria
            )
            ->get([
                'sgm_bitacora_verificacion_resultado.id',
                'sgm_bitacora_verificacion_resultado.id_lista',
                'sgm_bitacora_verificacion_resultado.resultado',
                'sgm_bitacora_verificacion_lista.pregunta',
            ]);

        $contenido .= '<tbody>';

        $contenido .= '<tr>';
        $contenido .= '<td valign="middle"><b>' . $this->e($categoria) . '</b></td>';
        $contenido .= '<td align="center" valign="middle"><b>Resultado</b></td>';
        $contenido .= '</tr>';

        if ($resultados->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="2" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            foreach ($resultados as $resultado) {
                $contenido .= '<tr>';

                $contenido .= '<td valign="middle">';
                $contenido .= $this->e($resultado->pregunta);
                $contenido .= '</td>';

                $contenido .= '<td valign="middle">';
                $contenido .= $this->e($resultado->resultado);
                $contenido .= '</td>';

                $contenido .= '</tr>';
            }
        }

        $contenido .= '</tbody>';

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
            return $hora;
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
