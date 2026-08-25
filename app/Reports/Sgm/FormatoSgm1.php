<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\ListaAsistencia;
use App\Models\Sasisopa\ListaAsistenciaDetalle;
use App\Models\Sasisopa\ListaAsistenciaEvidencia;
use App\Models\Sasisopa\ComunicacionIE;

class FormatoSgm1
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

        $registros = ListaAsistencia::query()
            ->from('tb_lista_asistencia')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->where('realizadopor', '<>', 0)
            ->get();

        foreach ($registros as $registro) {
            $idRegistro = (int) $registro->id;

            $realizadoPor = $this->realizadoPor((int) $registro->realizadopor);

            $comunicacion = ComunicacionIE::query()
                ->from('se_comunicacion_i_e')
                ->where('asistencia', $idRegistro)
                ->first();

            $material = $comunicacion?->material ?? '';

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Lista de Asistencia</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.001';
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

            /*
             * Datos de la comunicación
             */
            $contenido .= '<table class="table table-sm table-bordered">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fecha: ' . $this->formatoFecha($registro->fecha->format('Y-m-d'));
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Hora: ' . $this->formatoHora($registro->hora->format('g:i a'));
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Lugar: ' . $this->e($registro->lugar);
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" valign="middle">';
            $contenido .= '<b>Responsable de la comunicación:</b> ';
            $contenido .= $this->e($registro->encargado);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" valign="middle">';
            $contenido .= '<b>Tema a comunicar:</b> ';
            $contenido .= $this->e($registro->tema);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" valign="middle">';
            $contenido .= '<b>Finalidad de la comunicación:</b> ';
            $contenido .= $this->e($registro->finalidad);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3" valign="middle">';
            $contenido .= '<b>Material utilizado para la comunicación:</b> ';
            $contenido .= $this->e($material);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            /*
             * Lista de asistentes
             */
            $detalles = ListaAsistenciaDetalle::query()
                ->from('tb_lista_asistencia_detalle')
                ->where('id_lista_asistencia', $idRegistro)
                ->get();

            $contenido .= '<table class="table table-sm table-bordered">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle"><b>Nombre</b></td>';
            $contenido .= '<td align="center" valign="middle"><b>Puesto</b></td>';
            $contenido .= '<td align="center" valign="middle"><b>Firma</b></td>';
            $contenido .= '</tr>';

            if ($detalles->isNotEmpty()) {
                foreach ($detalles as $detalle) {
                    $firma = $this->buscarFirma(
                        (string) $detalle->usuario,
                        $idestacion
                    );

                    $firmaImagen = '';

                    if ($firma !== '') {
                        $rutaFirma = dirname(__DIR__, 2) . '/public/assets/img/firmas/' . $firma;
                        $firmaBase64 = $this->imagenBase64($rutaFirma);

                        if ($firmaBase64 !== '') {
                            $firmaImagen = '<img src="' . $firmaBase64 . '" width="50">';
                        }
                    }

                    $contenido .= '<tr>';

                    $contenido .= '<td align="center" valign="middle">';
                    $contenido .= $this->e($detalle->usuario);
                    $contenido .= '</td>';

                    $contenido .= '<td align="center" valign="middle">';
                    $contenido .= $this->e($detalle->puesto);
                    $contenido .= '</td>';

                    $contenido .= '<td align="center" valign="middle">';
                    $contenido .= $firmaImagen;
                    $contenido .= '</td>';

                    $contenido .= '</tr>';
                }
            } else {
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3" align="center">';
                $contenido .= 'No se encontró información';
                $contenido .= '</td>';
                $contenido .= '</tr>';
            }

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            $contenido .= '<br>';

            /*
             * Evidencias
             */
            $contenido .= '<div align="center"><b>Evidencia</b></div>';
            $contenido .= '<br>';

            $evidencias = ListaAsistenciaEvidencia::query()
                ->from('tb_lista_asistencia_evidencia')
                ->where('id_lista_asistencia', $idRegistro)
                ->get();

            foreach ($evidencias as $evidencia) {
                if (empty($evidencia->evidencia)) {
                    continue;
                }

                $rutaEvidencia = dirname(__DIR__, 2) . '/public/uploads/archivos/evidencias/' . $evidencia->evidencia;
                $evidenciaBase64 = $this->imagenBase64($rutaEvidencia);

                if ($evidenciaBase64 !== '') {
                    $contenido .= '<img src="' . $evidenciaBase64 . '" width="340">';
                    $contenido .= '<br><br>';
                }
            }

            $contenido .= '<br>';
        }

        $contenido .= '<hr><br>';

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

    private function buscarFirma(string $usuario, int $idestacion): string
    {
        return (string) (
            Usuario::query()
            ->from('tb_usuarios')
            ->where('id_gas', $idestacion)
            ->where('nombre', 'like', '%' . $usuario . '%')
            ->where('firma', '<>', '')
            ->value('firma') ?? ''
        );
    }

    private function formatoFecha(?string $fecha): string
    {
        if (
            empty($fecha) ||
            $fecha === '0000-00-00'
        ) {
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

    private function formatoHora(?string $hora): string
    {
        if (empty($hora)) {
            return '';
        }

        $timestamp = strtotime($hora);

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
