<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\SeguimientoObjetivoIndicador;
use App\Models\Sgm\SeguimientoImplementacionSgm;
use App\Models\Sgm\SeguimientoCalibracionEquipo;
use App\Models\Sgm\SeguimientoSatisfaccionCliente;
use App\Models\Sgm\SeguimientoAsistente;

class FormatoSgm4
{
    public function generar(int $idestacion, int $year): string
    {
        $contenido = '';

        $estacion = Estacion::query()
            ->from('tb_estaciones')
            ->select([
                'razonsocial',
                'apoderado_legal',
                'rfc',
                'direccioncompleta',
                'permisocre',
            ])
            ->where('id', $idestacion)
            ->first();

        if (!$estacion) {
            return '';
        }

        $registros = SeguimientoObjetivoIndicador::query()
            ->from('sgm_seguimiento_objetivo_indicador')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->get();

        foreach ($registros as $registro) {
            $idRegistro = (int) $registro->id;
            $realizadoPor = $this->realizadoPor((int) $registro->realizadopor);

            $yearFecha = $year;

            if ($registro->fecha instanceof \DateTimeInterface) {
                $yearFecha = (int) $registro->fecha->format('Y');
            } elseif (!empty($registro->fecha)) {
                $partes = explode('-', (string) $registro->fecha);

                if (!empty($partes[0])) {
                    $yearFecha = (int) $partes[0];
                }
            }

            /*
             * Encabezado Fo.SGM.004
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Seguimiento de objetivos e indicadores</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.004';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Realizado por: ' . $this->e($realizadoPor);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Revisado por:<br> Eduardo Galicia Flores';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Autorizado por:<br> ' . $this->e($estacion->apoderado_legal);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            $contenido .= $this->seguimiento($registro);

            $contenido .= $this->seguimientoSgm($idRegistro);

            $contenido .= $this->seguimientoCalibracionEquipo(
                $idRegistro,
                $yearFecha
            );

            $contenido .= $this->seguimientoSatisfaccionCliente($idRegistro);


            $contenido .= $this->asistentes($idRegistro);

            $contenido .= '<hr><br>';
        }

        return $contenido;
    }

    private function seguimiento(object $registro): string
    {
        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Fecha:</b></td>';
        $contenido .= '<td><b>Hora:</b></td>';
        $contenido .= '<td><b>Lugar:</b></td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>' . $this->formatoFecha($registro->fecha) . '</td>';
        $contenido .= '<td>' . $this->formatoHora($registro->hora) . '</td>';
        $contenido .= '<td>' . $this->e($registro->lugar) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</table>';

        return $contenido;
    }

    private function seguimientoSgm(int $idRegistro): string
    {
        $registro = SeguimientoImplementacionSgm::query()
            ->from('sgm_seguimiento_implementacion_sgm')
            ->where('id_seguimiento', $idRegistro)
            ->first();

        $respuestaUno = $registro?->respuesta_uno ?? '';
        $respuestaDos = $registro?->respuesta_dos ?? '';
        $respuestaTres = $registro?->respuesta_tres ?? '';
        $respuestaCuatro = $registro?->respuesta_cuatro ?? '';

        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= '<b>Indicador: Implementacion del SGM</b>';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>Porcentaje de procedimientos implementados durante el año inmediato anterior</td>';
        $contenido .= '<td>' . $this->e($respuestaUno) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>Porcentaje de procedimientos documentados durante el año inmediato anterior</td>';
        $contenido .= '<td>' . $this->e($respuestaDos) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">Comentarios y observacines:</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaTres) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= 'En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluacion';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaCuatro) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</table>';

        return $contenido;
    }

    private function seguimientoCalibracionEquipo(
        int $idRegistro,
        int $year
    ): string {
        $registro = SeguimientoCalibracionEquipo::query()
            ->from('sgm_seguimiento_calibracion_equipo')
            ->where('id_seguimiento', $idRegistro)
            ->first();

        $respuestaUno = $registro?->respuesta_uno ?? '';
        $respuestaDos = $registro?->respuesta_dos ?? '';
        $respuestaTres = $registro?->respuesta_tres ?? '';

        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= '<b>Indicador: Calibracion de equipos</b>';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>Porcentaje de quipos calibrados durante el año ' . $year . '</td>';
        $contenido .= '<td>' . $this->e($respuestaUno) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">Comentarios y observacines:</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaDos) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= 'En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluacion';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaTres) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</table>';

        return $contenido;
    }

    private function seguimientoSatisfaccionCliente(int $idRegistro): string
    {
        $registro = SeguimientoSatisfaccionCliente::query()
            ->from('sgm_seguimiento_satisfaccion_cliente')
            ->where('id_seguimiento', $idRegistro)
            ->first();

        $respuestaUno = $registro?->respuesta_uno ?? '';
        $respuestaDos = $registro?->respuesta_dos ?? '';
        $respuestaTres = $registro?->respuesta_tres ?? '';
        $respuestaCuatro = $registro?->respuesta_cuatro ?? '';
        $respuestaCinco = $registro?->respuesta_cinco ?? '';

        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= '<b>Indicador: Satisfaccion del cliente</b>';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>Numero de quejas por parte de los clientes</td>';
        $contenido .= '<td>' . $this->e($respuestaUno) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>Numero de quejas atendidas de manera satisfactoria</td>';
        $contenido .= '<td>' . $this->e($respuestaDos) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>';
        $contenido .= 'Si ya se cuenta con resultados del año inmediato anterior determinar el procentaje que representan las quejas del año inmediato anterior contra los resultados con los que cuenta la estacion de servicio';
        $contenido .= '</td>';
        $contenido .= '<td>' . $this->e($respuestaTres) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">Comentarios y observacines:</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaCuatro) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">';
        $contenido .= 'En caso de no obtener resultados favorables, describa las acciones a tomar junto con los recursos que necesita con la finalidad de cambiar los resultados obtenidos para la siguiente evaluacion';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td colspan="2">' . $this->e($respuestaCinco) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</table>';

        return $contenido;
    }

    private function asistentes(int $idRegistro): string
    {
        $asistentes = SeguimientoAsistente::query()
            ->from('sgm_seguimiento_asistentes')
            ->join(
                'tb_usuarios',
                'sgm_seguimiento_asistentes.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where(
                'sgm_seguimiento_asistentes.id_seguimiento',
                $idRegistro
            )
            ->select([
                'sgm_seguimiento_asistentes.id',
                'tb_usuarios.nombre',
                'tb_usuarios.firma',
            ])
            ->get();

        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<thead>';
        $contenido .= '<tr>';
        $contenido .= '<th align="center">#</th>';
        $contenido .= '<th align="center">Nombre</th>';
        $contenido .= '<th align="center">Firma</th>';
        $contenido .= '</tr>';
        $contenido .= '</thead>';

        $contenido .= '<tbody>';

        if ($asistentes->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($asistentes as $asistente) {
                $firmaImagen = '';

                if (!empty($asistente->firma)) {
                    $rutaFirma = dirname(__DIR__, 2) . '/public/assets/img/firmas/' . $asistente->firma;
                    $base64 = $this->imagenBase64($rutaFirma);

                    if ($base64 !== '') {
                        $firmaImagen = '<img src="' . $base64 . '" width="50">';
                    }
                }

                $contenido .= '<tr>';
                $contenido .= '<td align="center" valign="middle">' . $numero . '</td>';
                $contenido .= '<td valign="middle">' . $this->e($asistente->nombre) . '</td>';
                $contenido .= '<td align="center" valign="middle">' . $firmaImagen . '</td>';
                $contenido .= '</tr>';

                $numero++;
            }
        }

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        return $contenido;
    }

    private function realizadoPor(int $usuario): string
    {
        return (string) (
            Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $usuario)
            ->value('nombre') ?? ''
        );
    }

    private function formatoFecha($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            $fecha = $fecha->format('Y-m-d');
        }

        $fecha = (string) ($fecha ?? '');

        if ($fecha === '' || $fecha === '0000-00-00') {
            return 'S/I';
        }

        $partes = explode('-', $fecha);

        if (count($partes) !== 3) {
            return $this->e($fecha);
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

        return $this->e(
            $partes[2] . ' de ' . $mes . ' del ' . $partes[0]
        );
    }

    private function formatoHora($hora): string
    {
        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('g:i a');
        }

        if (empty($hora)) {
            return '';
        }

        $timestamp = strtotime((string) $hora);

        if ($timestamp === false) {
            return $this->e($hora);
        }

        return date('g:i a', $timestamp);
    }

    private function imagenBase64(string $ruta): string
    {
        if (
            $ruta === '' ||
            !is_file($ruta) ||
            !is_readable($ruta)
        ) {
            return '';
        }

        $datos = file_get_contents($ruta);

        if ($datos === false) {
            return '';
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($datos);
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
