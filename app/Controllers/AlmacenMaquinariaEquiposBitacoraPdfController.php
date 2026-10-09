<?php

namespace App\Controllers;

use App\Services\AlmacenMaquinariaEquiposBitacoraService as Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDFs de la Bitácora de Maquinaria y Equipos — Almacén
 *
 * Replica EXACTA de los PDFs del legacy:
 *   - descargar-bitacora-pdf.php          → índice/registro (letter portrait)
 *     nombre: "Registro {orden} - Bitacora maquinaria.pdf"
 *   - descargar-bitacora-pdf-general.php  → matriz del ciclo Diario
 *     nombre: "Bitacora maquinaria y equipo (Diario).pdf" (letter landscape)
 *
 * Mismo diseño (tipografías, paddings, colores, tablas), mismo contenido
 * (mismos campos, mismas columnas condicionales, mismas secciones de firmas)
 * y mismo nombre de descarga.
 */
class AlmacenMaquinariaEquiposBitacoraPdfController
{
    /* ------------------------------------------------------------------ */
    /* Registro                                                           */
    /* ------------------------------------------------------------------ */

    public function indexRegistro(int $id): void
    {
        $d = Service::getDatosPdfRegistro($id);

        if ($d === null) {
            http_response_code(404);
            echo 'Registro no disponible (debe estar Finalizado).';
            exit;
        }

        $equipo    = (array)($d['equipo'] ?? []);
        $frec      = (string)$d['frecuencia_label'];
        $orden     = (int)$d['orden'];
        $actividades = (array)($d['actividades'] ?? []);

        // Columnas condicionales, misma regla que el legacy/detalle.
        $tieneRevisado = false;
        $tieneCambio   = false;

        foreach ($actividades as $a) {
            if ((string)$a['revision_tipo'] === 'revisado') {
                $tieneRevisado = true;
            } elseif (in_array((string)$a['revision_tipo'], ['select', 'texto'], true)) {
                $tieneCambio = true;
            }
        }

        $colRevisado = ($frec !== 'Diario' && $tieneRevisado);
        $colCambio   = $tieneCambio;

        $h  = $this->apertura('Registro ' . $orden . ' - Bitacora maquinaria', 0.85);
        $h .= '<div class="text-center"><div class="titulo">Dirección de operaciones</div>'
            . '<div class="text-secondary mt-2">' . $this->e($d['estacion']) . '</div></div>';
        $h .= '<div class="text-center font-weight-bold mt-3" style="font-size:1.1em;">Registro ' . $orden . '</div>';

        // ---- Información ----
        $h .= '<table class="table-sm mt-2" style="width: 100%;"><tbody>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>EQUIPO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['descripcion_equipo']) . '</div></td>'
            . '<td><div class="text-secondary"><b>MAQUINARIA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['maquinaria']) . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>MARCA / MODELO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e(($d['marca'] ?? '') . ' ' . ($d['modelo'] ?? '')) . '</div></td>'
            . '<td><div class="text-secondary"><b>NO. DE SERIE:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['no_serie'] ?? ($equipo['no_serie'] ?? '')) . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>ESTADO ACTUAL:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['estado_actual_label']) . '</div></td>'
            . '<td><div class="text-secondary"><b>FECHA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['fecha_label']) . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>TIPO DE MANTENIMIENTO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['tipo_label']) . '</div></td>'
            . '<td><div class="text-secondary"><b>FRECUENCIA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($frec) . '</div></td>'
            . '</tr>';
        $h .= '<tr><td colspan="2"><div class="text-secondary"><b>COSTO DE MANTENIMIENTO:</b></div><div class="mt-2 pb-1 border-bottom">$ ' . $this->e(number_format((float)$d['costo'], 2)) . '</div></td></tr>';
        $h .= '<tr><td colspan="2"><div class="text-secondary"><b>EN CASO DE SER CORRECTIVO, CUÁL FUE LA FALLA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['falla_descripcion']) . '</div></td></tr>';
        $h .= '<tr><td colspan="2"><div class="text-secondary"><b>OBSERVACIONES:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($d['observaciones']) . '</div></td></tr>';
        $h .= '</tbody></table>';

        // ---- Actividades ----
        $colspanPdf = 3 + ($colRevisado ? 1 : 0) + ($colCambio ? 1 : 0);

        $h .= '<table class="table-bordered mt-2">';
        $h .= '<thead><tr><th style="width:30px;" class="p-1">#</th><th class="p-1">Actividad</th><th class="p-1 text-center">SI / NO</th>';
        if ($colRevisado) {
            $h .= '<th class="p-1">Revisado por</th>';
        }
        if ($colCambio) {
            $h .= '<th class="p-1">Cambiado por</th>';
        }
        $h .= '</tr></thead><tbody>';

        $iAct = 1;
        $nombresUsuarios = $this->mapaUsuarios((array)($d['usuarios_estacion'] ?? []));

        foreach ($actividades as $a) {
            $tipo = (string)$a['revision_tipo'];

            if (in_array($tipo, ['titulo', 'subtitulo', 'nota'], true)) {
                $estilo = $tipo === 'titulo'
                    ? 'font-weight:bold;'
                    : ($tipo === 'subtitulo' ? 'font-weight:bold;color:#6c757d;' : 'font-style:italic;color:#6c757d;');
                $h .= '<tr><td colspan="' . $colspanPdf . '" class="p-1" style="' . $estilo . '">' . $this->e($a['descripcion']) . '</td></tr>';
                continue;
            }

            $resultado = ($tipo === 'checkbox')
                ? ((int)$a['resultado'] === 1 ? 'SI' : 'NO')
                : (!empty($a['revisado_por']) || !empty($a['cambiado_por_texto']) ? 'SI' : 'NO');

            $sp = $resultado === 'SI'
                ? '<span class="st-si">SI</span>'
                : '<span class="st-no">NO</span>';

            $quienRevisado = '';
            $quienCambio   = '';

            if ($colRevisado) {
                $quienRevisado = ($tipo === 'revisado')
                    ? ($nombresUsuarios[(int)$a['revisado_por']] ?? 'Sin informacion')
                    : 'Sin informacion';
            }

            if ($colCambio) {
                if ($tipo === 'select') {
                    $quienCambio = $nombresUsuarios[(int)$a['revisado_por']] ?? 'Sin informacion';
                } elseif ($tipo === 'texto') {
                    $quienCambio = trim((string)$a['cambiado_por_texto']) !== '' ? (string)$a['cambiado_por_texto'] : 'Sin informacion';
                } else {
                    $quienCambio = 'Sin informacion';
                }
            }

            $h .= '<tr><td class="p-1">' . $iAct . '</td><td class="p-1">' . $this->e($a['descripcion'])
                . '</td><td class="p-1 text-center">' . $sp . '</td>';
            if ($colRevisado) {
                $h .= '<td class="p-1">' . $this->e($quienRevisado) . '</td>';
            }
            if ($colCambio) {
                $h .= '<td class="p-1">' . $this->e($quienCambio) . '</td>';
            }
            $h .= '</tr>';

            $iAct++;
        }

        $h .= '</tbody></table>';

        // ---- Firmas (3 columnas, igual que el detalle del legacy) ----
        $h .= '<table class="mt-3" style="width:100%; border-collapse: separate; border-spacing: 4px;"><tbody><tr>';
        $h .= $this->celdasFirmas((array)($d['firmas_rows'] ?? []), 120, true);
        $h .= '</tr></tbody></table>';

        $h .= '</body></html>';

        $this->salir($h, 'Registro ' . $orden . ' - Bitacora maquinaria.pdf', false);
    }

    /* ------------------------------------------------------------------ */
    /* Ciclo diario (matriz)                                              */
    /* ------------------------------------------------------------------ */

    public function general(int $id): void
    {
        $datos = Service::getDatosPdfGeneral($id);

        if ($datos === null) {
            http_response_code(404);
            echo 'Ciclo no disponible (solo frecuencias Diarias).';
            exit;
        }

        $m   = (array)($datos['mantenimiento'] ?? []);
        $eq  = (array)($datos['equipo'] ?? []);
        $filas = (array)($datos['filas'] ?? []);
        $dias  = (array)($datos['dias'] ?? []);

        $frecLabel = [1 => 'Diario', 2 => 'Semanal', 3 => 'Por horas', 4 => 'Mensual'];
        $frec = $frecLabel[(int)($m['frecuencia'] ?? 0)] ?? '-';

        $perFin = (string)($m['fecha_fin_label'] ?? '');
        if ($perFin === '') {
            $perFin = count($dias) ? (string)end($dias)['fecha_label'] : (string)($m['fecha_inicio_label'] ?? '');
        }

        $h  = $this->apertura('Bitacora maquinaria y equipo (Diario)', 0.82);
        $h .= '<h3 class="subtitulo mt-2">Bitácora de mantenimiento de maquinaria y equipos</h3>';
        $h .= '<div class="text-secondary mt-2">' . $this->e($eq['estacion'] ?? ($m['estacion'] ?? '')) . '</div>';

        // ---- Información (2 columnas por fila, como el legacy) ----
        $h .= '<table class="table-sm mt-2" style="width: 100%;"><tbody>';
        $h .= '<tr>'
            . '<td style="width:50%;"><div class="text-secondary"><b>EQUIPO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($eq['descripcion'] ?? '') . '</div></td>'
            . '<td style="width:50%;"><div class="text-secondary"><b>MAQUINARIA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($eq['maquinaria'] ?? '') . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>MARCA / MODELO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')) . '</div></td>'
            . '<td><div class="text-secondary"><b>NO. DE SERIE:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($eq['no_serie'] ?? '') . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>TIPO DE MANTENIMIENTO:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($m['tipo_label'] ?? '') . '</div></td>'
            . '<td><div class="text-secondary"><b>FRECUENCIA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($frec) . '</div></td>'
            . '</tr>';
        $h .= '<tr>'
            . '<td><div class="text-secondary"><b>PERIODO (INICIO):</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($m['fecha_inicio_label'] ?? '') . '</div></td>'
            . '<td><div class="text-secondary"><b>PERIODO (FIN):</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($perFin) . '</div></td>'
            . '</tr>';
        if (trim((string)($m['falla_descripcion'] ?? '')) !== '') {
            $h .= '<tr><td colspan="2"><div class="text-secondary"><b>EN CASO DE SER CORRECTIVO, CUÁL FUE LA FALLA:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($m['falla_descripcion']) . '</div></td></tr>';
        }
        if (trim((string)($m['observaciones'] ?? '')) !== '' && (string)$m['observaciones'] !== '0') {
            $h .= '<tr><td colspan="2"><div class="text-secondary"><b>OBSERVACIONES:</b></div><div class="mt-2 pb-1 border-bottom">' . $this->e($m['observaciones']) . '</div></td></tr>';
        }
        $h .= '</tbody></table>';

        // ---- Matriz ----
        $h .= '<div class="mt-3 text-secondary" style="font-weight:bold;">DIARIO</div>';
        $h .= '<table class="table-bordered mt-2" style="font-size:.75rem;"><thead><tr>';
        $h .= '<th class="p-1 text-center" style="width:28px;">#</th>';
        $h .= '<th class="p-1 text-left">Actividad</th>';
        foreach ($dias as $d) {
            $h .= '<th class="p-1 text-center"><small>' . $this->e($d['fecha_label_corta'] ?? $d['fecha_label']) . '</small></th>';
        }
        $h .= '</tr></thead><tbody>';

        foreach ($filas as $i => $fila) {
            $h .= '<tr><td class="p-1 text-center">' . ($i + 1) . '</td>';
            $h .= '<td class="p-1">' . $this->e($fila['descripcion']) . '</td>';

            foreach ($dias as $d) {
                $valor = (string)($d['celdas'][$i] ?? '');

                if ($valor === 'SI') {
                    $celda = '<span class="st-si">SI</span>';
                } elseif ($valor === 'NO') {
                    $celda = '<span class="st-no">NO</span>';
                } else {
                    $celda = '';
                }

                $h .= '<td class="p-1 text-center">' . $celda . '</td>';
            }

            $h .= '</tr>';
        }

        $h .= '</tbody></table>';

        // ---- Firmas ----
        $h .= '<div class="mt-3 text-secondary" style="font-weight:bold;">FIRMAS</div>';
        $h .= '<table class="mt-2" style="width:100%; border-collapse: separate; border-spacing: 4px;"><tbody><tr>';
        $h .= $this->celdasFirmas($this->firmasGenerales((array)($datos['firmas'] ?? [])), 110, false);
        $h .= '</tr></tbody></table>';

        $h .= '</body></html>';

        $this->salir($h, 'Bitacora maquinaria y equipo (Diario).pdf', true);
    }

    /* ------------------------------------------------------------------ */
    /* Fragmentos                                                         */
    /* ------------------------------------------------------------------ */

    /** Se repite el CSS del legacy (mismas clases y medidas). */
    private function apertura(string $titulo, float $rem): string
    {
        $remTxt = rtrim(rtrim(number_format($rem, 2, '.', ''), '0'), '.');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' . $this->e($titulo) . '</title>'
            . '<style type="text/css">'
            . '@page {margin: 1cm 1cm;}'
            . 'body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: ' . $remTxt . 'rem; color: #212529; }'
            . '.text-center { text-align: center !important; }'
            . '.text-secondary { color: #6c757d !important; }'
            . '.mt-2 { margin-top: 12px !important; }'
            . '.mt-3 { margin-top: 18px !important; }'
            . '.mb-0 { margin-bottom: 0 !important; }'
            . '.mb-2 { margin-bottom: 10px !important; }'
            . '.p-1 { padding: 4px !important; }'
            . '.p-2 { padding: 8px !important; }'
            . '.pb-1 { padding-bottom: 4px !important; }'
            . '.border-bottom { border-bottom: 1px solid #dee2e6 !important; }'
            . 'table { border-collapse: collapse; width: 100%; }'
            . '.table-sm th, .table-sm td { padding: 4px 6px; }'
            . '.table-bordered { border: 1px solid #dee2e6; }'
            . '.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }'
            . '.titulo { font-size: 1.4em; font-weight: bold; }'
            . '.subtitulo { font-size: 1em; }'
            . '.st-si { color: #0b7a34; font-weight: bold; }'
            . '.st-no { color: #b02a37; font-weight: bold; }'
            . '</style></head><body>';
    }

    /**
     * Las tres celdas de firmas del legacy: encabezado con el nombre de quien
     * firmó, la imagen (o el texto del medio electrónico) y la leyenda.
     */
    private function celdasFirmas(array $firmasRows, int $anchoImg, bool $conFechaEnLeyenda): string
    {
        $mapa = [];

        foreach ($firmasRows as $f) {
            $mapa[(string)($f['tipo_firma'] ?? '')] = $f;
        }

        $labels = [
            'A' => '<b>NOMBRE Y FIRMA DE QUIEN ELABORA</b>',
            'B' => '<b>NOMBRE Y FIRMA DE VOBO</b>',
            'C' => '<b>NOMBRE Y FIRMA DE AUTORIZACIÓN</b>',
        ];

        $html = '';

        foreach ($labels as $tipo => $label) {
            $f = $mapa[$tipo] ?? null;

            $encabezado = '';
            $detalle    = '';

            if ($f) {
                $encabezado = (string)($f['nombre'] ?? '');

                $fechaFirma = $this->fechaFirmaTexto((string)($f['fecha_label'] ?? ''));

                $img = '';

                if (!empty($f['es_imagen'])) {
                    $ruta = Service::getFirmasDir() . (string)($f['firma'] ?? '');

                    if (is_file($ruta)) {
                        $img = '<div class="text-center"><img src="' . $this->dataUri($ruta) . '" style="width: ' . $anchoImg . 'px;"></div>';
                    }
                }

                if ($img !== '') {
                    // A y B dibujada: imagen + texto con la fecha debajo.
                    $medio = ($tipo === 'A') ? 'digital' : 'electrónico';
                    $detalle = $img . ($conFechaEnLeyenda
                        ? '<div class="text-center fw-normal" style="font-size:.7rem;margin-top:4px;">El registro se firmó por un medio ' . $medio . '.<br><b>Fecha: ' . $this->e($fechaFirma) . '</b></div>'
                        : '');
                } else {
                    $medio = ($tipo === 'A') ? 'digital' : 'electrónico';
                    $detalle = '<div class="text-center" style="font-size:.7rem;"><small>El registro se firmó por un medio ' . $medio . '<br><b>Fecha: ' . $this->e($fechaFirma) . '</b></small></div>';
                }
            } else {
                $detalle = '<div class="text-center" style="font-size:.75rem;"><small>Sin firma</small></div>';
            }

            $html .= '<td class="text-center" style="border:1px solid #dee2e6; vertical-align:top;">';
            $html .= '<div class="p-1" style="background:#e9ecef; font-weight:bold; border-bottom:1px solid #dee2e6;">' . $this->e($encabezado) . '</div>';
            $html .= '<div class="p-1">' . $detalle . '</div>';
            $html .= '<div class="mt-1 p-1" style="font-size:' . ($conFechaEnLeyenda ? '.8em' : '.75rem') . ';">' . $label . '</div>';
            $html .= '</td>';
        }

        return $html;
    }

    private function firmasGenerales(array $firmas): array
    {
        $rows = [];

        foreach (['A', 'B', 'C'] as $tipo) {
            $f = $firmas[$tipo] ?? null;

            if (empty($f)) {
                continue;
            }

            $rows[] = [
                'tipo_firma'  => $tipo,
                'nombre'      => $f['nombre'] ?? '',
                'fecha_label' => $f['fecha_label'] ?? '',
                'fecha'       => $f['fecha'] ?? '',
                'firma'       => $f['firma'] ?? '',
                'es_imagen'   => !empty($f['es_imagen']),
            ];
        }

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function mapaUsuarios(array $usuarios): array
    {
        $mapa = [];

        foreach ($usuarios as $u) {
            $mapa[(int)($u['id'] ?? 0)] = (string)($u['nombre'] ?? '');
        }

        return $mapa;
    }

    /** "04 de Septiembre del 2026, 3:51 pm" → "04 de Septiembre del 2026, 3:51 pm" */
    private function fechaFirmaTexto(string $label): string
    {
        return trim($label);
    }

    private function salir(string $html, string $nombrePdf, bool $landscape): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', $landscape ? 'landscape' : 'portrait');
        $dompdf->render();

        // Pie de página igual al del registro del legacy.
        $dompdf->get_canvas()->page_text(35, $landscape ? 560 : 770, 'Página: {PAGE_NUM} de {PAGE_COUNT}', 'helvetica', 7, [100, 100, 100]);

        $dompdf->stream($nombrePdf, ['Attachment' => true]);
        exit;
    }

    private function dataUri(string $ruta): string
    {
        $datos = @file_get_contents($ruta);

        if ($datos === false || $datos === '') {
            return '';
        }

        $mime = function_exists('mime_content_type') ? (string)mime_content_type($ruta) : '';

        if ($mime === '' || $mime === false) {
            $mime = 'image/png';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($datos);
    }

    private function e(?string $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}
