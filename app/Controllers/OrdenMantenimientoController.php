<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\ModuleStationService;
use App\Services\OrdenMantenimientoService as Service;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class OrdenMantenimientoController extends BaseController
{
    private const BASE = '/departamento-operativo/almacen/orden-mantenimiento';
    private const TITLE = 'Orden de Mantenimiento';

    public function index(?int $id = null): void
    {
        $permissions = Service::permissions();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add(self::TITLE, '');

        if (!$this->guardModuleAccess(Service::MODULE_KEY, self::TITLE, 'departamento-operativo')) return;
        $context = ModuleStationService::getContext(Service::MODULE_KEY);
        $selected = $context['id_estacion'] ?? $context['id_depto'] ?? null;
        $record = $id ? Service::detail($id) : null;
        if ($id && $record === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Registro no encontrado'], 'departamento-operativo');
            return;
        }

        $view = $id === null
            ? 'departamento-operativo/4-almacen/orden-mantenimiento/index'
            : 'departamento-operativo/4-almacen/orden-mantenimiento/formulario';

        View::render($view, [
            'title' => self::TITLE,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija' => $selected !== null,
            'estacionActual' => (int)($selected ?? 0),
            'ocultarSelectorEstacion' => $id !== null,
            'nombreBadgeEstacion' => $id !== null ? (string)($record['estacion'] ?? '') : '',
            'registro' => $record,
            'permisos' => $permissions,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/libs/select2/dist/js/i18n/es.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/departamento-operativo/4-almacen/orden-mantenimiento.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function signPage(int $id): void
    {
        if (!Service::permissions()['leer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $detail = Service::detail($id);
        if ($detail === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Orden no encontrada'], 'departamento-operativo');
            return;
        }

        $signType = Service::signingType($id);
        if ($signType === null) {
            http_response_code(403);
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar Orden de Mantenimiento (#'.$detail['folio_label'].')';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add(self::TITLE, self::BASE);
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/orden-mantenimiento/firmar', [
            'title' => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija' => true,
            'estacionActual' => (int)$detail['id_estacion'],
            'ocultarSelectorEstacion' => true,
            'nombreBadgeEstacion' => (string)$detail['estacion'],
            'detalle' => $detail,
            'tipoFirma' => $signType,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/orden-mantenimiento-firma.init.js?v=' . time(),
            ],
            'links' => [],
        ], 'departamento-operativo');
    }

    public function data(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso para consultar Orden de Mantenimiento.');
        JsonResponse::custom(['success' => true, 'data' => Service::getData()]);
    }

    public function create(): void
    {
        if (!Service::permissions()['crear']) JsonResponse::forbidden('No tienes permiso para crear solicitudes.');
        $result = Service::create();
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function detail(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso de lectura.');
        $detail = Service::detail((int)(Request::input('id') ?? 0));
        if ($detail === null) JsonResponse::notFound('Registro no encontrado o fuera de tu alcance.');
        JsonResponse::custom(['success' => true, 'data' => $detail]);
    }

    public function update(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para editar solicitudes.');
        $result = Service::update(
            (int)(Request::input('id') ?? 0),
            Request::all(),
            (string)(Request::input('autosave') ?? '') !== '1'
        );
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function comments(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso de lectura.');
        $id = (int)(Request::input('id') ?? 0);
        $comments = Service::comments($id);
        if ($comments === null) JsonResponse::notFound('Registro no encontrado o fuera de tu alcance.');
        JsonResponse::custom(['success' => true, 'detalle' => Service::detail($id), 'data' => $comments]);
    }

    public function addComment(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso para comentar.');
        $result = Service::addComment((int)(Request::input('id') ?? 0), (string)(Request::input('comentario') ?? ''));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function toggleArea(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para editar áreas.');
        $result = Service::toggleArea((int)(Request::input('id_area') ?? 0), (int)(Request::input('estatus') ?? -1));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function toggleSubarea(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para editar subáreas.');
        $result = Service::toggleSubarea((int)(Request::input('id_subarea') ?? 0), (int)(Request::input('estatus') ?? -1));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function addArea(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para agregar áreas.');
        $result = Service::addArea((int)(Request::input('id') ?? 0), (string)(Request::input('area') ?? ''));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function refacciones(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso de lectura.');
        $id = (int)(Request::input('id') ?? 0);
        $data = Service::refacciones($id);
        if ($data === null) JsonResponse::notFound('Registro no encontrado o fuera de tu alcance.');
        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function addMaterial(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para editar refacciones.');
        $result = Service::addMaterial((int)(Request::input('id') ?? 0), Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteMaterial(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para quitar refacciones.');
        $result = Service::deleteMaterial((int)(Request::input('id') ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function files(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso de lectura.');
        $id = (int)(Request::input('id') ?? 0);
        $data = Service::files($id);
        if ($data === null) JsonResponse::notFound('Registro no encontrado o fuera de tu alcance.');
        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function storeInstallation(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para registrar evidencia.');
        $result = Service::storeInstallation((int)(Request::input('id') ?? 0), $_FILES['archivo'] ?? []);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteInstallation(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para eliminar evidencia.');
        $result = Service::deleteInstallation((int)(Request::input('id') ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function storeEvidence(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para agregar evidencia.');
        $result = Service::storeEvidence((int)(Request::input('id') ?? 0), Request::all(), $_FILES['archivo'] ?? []);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteEvidence(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para eliminar evidencia.');
        $result = Service::deleteEvidence((int)(Request::input('id') ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function addCause(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para registrar una causa.');
        $result = Service::addCause((int)(Request::input('id') ?? 0), Request::all(), $_FILES['factura_pdf'] ?? [], $_FILES['factura_xml'] ?? []);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteCause(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para eliminar una causa.');
        $result = Service::deleteCause((int)(Request::input('id') ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function finalize(): void
    {
        if (!Service::permissions()['editar']) JsonResponse::forbidden('No tienes permiso para finalizar órdenes.');
        $result = Service::finalize((int)(Request::input('id') ?? 0), Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function createSignatureToken(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso para firmar.');
        $result = Service::createSignatureToken((int)(Request::input('id') ?? 0), (string)(Request::input('canal') ?? ''));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function sign(): void
    {
        if (!Service::permissions()['leer']) JsonResponse::forbidden('No tienes permiso para firmar.');
        $result = Service::sign((int)(Request::input('id') ?? 0), (string)(Request::input('token') ?? ''));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        $result = Service::destroy((int)(Request::input('id') ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function pdf(int $id): void
    {
        if (!Service::permissions()['descargar']) {
            http_response_code(403);
            echo 'No tienes permiso para descargar esta orden.';
            return;
        }
        $data = Service::exportData($id);
        if ($data === null) {
            http_response_code(404);
            echo 'Orden no encontrada o fuera de tu alcance.';
            return;
        }
        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $projectRoot = dirname(__DIR__, 2);
        $imageDataUri = static function (string $filename) use ($projectRoot): ?string {
            $filename = basename($filename);
            if ($filename === '') return null;
            $path = $projectRoot.'/public/uploads/archivos/'.$filename;
            if (!is_file($path)) return null;
            $mime = function_exists('mime_content_type') ? (string)mime_content_type($path) : '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) return null;
            $contents = file_get_contents($path);
            return $contents === false ? null : 'data:'.$mime.';base64,'.base64_encode($contents);
        };

        $logoPath = $projectRoot.'/public/assets/images/logos/Logo.png';
        $logoContents = is_file($logoPath) ? file_get_contents($logoPath) : false;
        $logo = $logoContents === false ? '' : '<img class="logo" src="data:image/png;base64,'.base64_encode($logoContents).'" alt="AdmonGas">';
        $folioControl = '00'.(int)$data['folio'];

        $serviceType = (int)$data['tipo_servicio'];
        $workType = (int)$data['orden_trabajo'];
        $riskType = (int)$data['orden_riesgo'];
        $check = static fn(bool $selected): string => $selected ? '<span class="check">✓</span>' : '';
        $affectedLabel = (int)$data['id_estacion'] === 9
            ? '¿EN QUÉ AFECTA AL AUTOLAVADO?'
            : '¿EN QUÉ AFECTA A LA ESTACIÓN?';
        $pdfTable = static function (string $title, array $headers, string $rows, int $columns, string $emptyMessage = 'No se encontró información para mostrar.') use ($e): string {
            $head = '<thead>';
            if ($title !== '') $head .= '<tr><th class="table-title" colspan="'.$columns.'">'.$e($title).'</th></tr>';
            if ($headers !== []) {
                $head .= '<tr>'.implode('', array_map(static fn($header): string => '<th class="center">'.$e($header).'</th>', $headers)).'</tr>';
            }
            $head .= '</thead>';
            if ($rows === '') $rows = '<tr><td colspan="'.$columns.'" class="center">'.$e($emptyMessage).'</td></tr>';
            return '<table class="detail-table">'.$head.'<tbody>'.$rows.'</tbody></table>';
        };
        $choiceTable = static function (string $title, array $labels, int $selected) use ($check, $pdfTable): string {
            $rows = '';
            if ($selected >= 1 && $selected <= count($labels)) {
                $rows = '<tr>'.implode('', array_map(
                    static fn($index): string => '<td class="center">'.$check($selected === $index + 1).'</td>',
                    array_keys($labels)
                )).'</tr>';
            }
            return $pdfTable($title, $labels, $rows, count($labels), 'No se encontró información seleccionada.');
        };

        $areaRows = '';
        if ((int)$data['id_estacion'] !== 9) {
            foreach ($data['areas'] as $area) {
                $subareas = array_map(static fn($subarea): string => (string)($subarea['nombre'] ?? ''), $area['subareas'] ?? []);
                $subareaText = $subareas === [] ? '' : ' <span class="subarea">('.$e(implode(', ', $subareas)).')</span>';
                $areaRows .= '<tr><td>'.$e($area['area'] ?? '').$subareaText.'</td><td class="center">'.$check((int)($area['estatus'] ?? 0) === 1).'</td></tr>';
            }
        }

        $materialRows = '';
        foreach ($data['materiales'] as $item) {
            $materialRows .= '<tr><td>'.$e($item['concepto'] ?? '').'</td><td class="center">'.$e($item['cantidad'] ?? '').'</td><td>'.$e($item['nota'] ?? '').'</td></tr>';
        }
        $evidenceRows = '';
        foreach ($data['evidencias'] as $evidence) {
            $filename = basename((string)($evidence['archivo'] ?? ''));
            $image = $imageDataUri($filename);
            $fileContent = $image !== null
                ? '<img class="evidence-image" src="'.$image.'" alt="Evidencia">'
                : '<span class="file-name">'.$e($filename !== '' ? $filename : 'Archivo adjunto').'</span>';
            $evidenceRows .= '<tr><td class="center">'.$fileContent.'</td><td class="center">'.$e($evidence['area'] ?? 'S/I').'</td><td class="center">'.$e($evidence['motivo'] ?? 'S/I').'</td></tr>';
        }
        $signatureCells = '';
        foreach ($data['firmas'] as $signature) {
            $file = '';
            parse_str((string)parse_url((string)($signature['firma_url'] ?? ''), PHP_URL_QUERY), $signatureQuery);
            $file = basename((string)($signatureQuery['file'] ?? ''));
            $signatureImage = ($signature['tipo'] ?? '') === 'A' ? $imageDataUri($file) : null;
            if (($signature['tipo'] ?? '') === 'A' && $signatureImage !== null) {
                $signatureBody = '<img class="signature-image" src="'.$signatureImage.'" alt="Firma">';
            } elseif (!empty($signature['electronica'])) {
                $signatureBody = '<div class="signature-note">Firmado por medio electrónico.<br><b>Fecha: '.$e($signature['fecha'] ?? 'S/I').'</b></div>';
            } else {
                $signatureBody = '<div class="signature-note">La firma está registrada, pero su imagen no está disponible.</div>';
            }
            $signatureCells .= '<td class="signature-cell"><div class="signature-name">'.$e($signature['usuario'] ?? 'Usuario').'</div>'.$signatureBody.'<div class="signature-role">'.$e($signature['titulo'] ?? 'Firma').'</div></td>';
        }
        $signatureRows = $signatureCells === '' ? '' : '<tr>'.$signatureCells.'</tr>';
        $signatureColumnCount = max(1, count($data['firmas']));

        $html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
            @page{margin:14mm 12mm 17mm}*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;font-size:10px;line-height:1.3;color:#212529;margin:0}
            .logo{width:145px;height:auto;margin:0 0 5px}table{width:100%;border-collapse:collapse;margin:6px 0;page-break-inside:auto}tr{page-break-inside:avoid;page-break-after:auto}th,td{border:1px solid #d6dce2;padding:5px 6px;vertical-align:middle;font-size:10px}th{font-weight:bold;background:#eaf0f6}.detail-table{margin:8px 0}.detail-table .table-title{text-align:center;font-weight:bold;background:#eaf0f6;padding:6px}.detail-table tbody tr:nth-child(odd) td{background:#f6f8fa}.center{text-align:center}.muted{color:#68737d}.subarea{color:#68737d;font-size:9px}.header-table td{padding:5px 6px}.header-title{text-align:center;font-weight:bold}.station-title{text-align:center;font-weight:bold;background:#eaf0f6}.check{color:#169447;font-size:12px;font-weight:bold}.evidence-image{width:70px;height:70px;object-fit:contain}.file-name{font-size:9px;word-break:break-all}.signature-cell{width:33.33%;text-align:center;vertical-align:top;padding:5px}.signature-name{font-weight:bold;background:#eef1f4;padding:6px;margin-bottom:4px}.signature-image{max-width:150px;max-height:75px;object-fit:contain;margin:8px auto}.signature-note{font-size:9px;color:#505a64;padding:7px 3px}.signature-role{font-weight:bold;border-top:1px solid #aeb8c2;padding-top:6px;margin-top:4px;font-size:9px}.signature-table{page-break-inside:avoid}.text-right{text-align:right}
            </style></head><body>'.$logo
            .'<table class="header-table"><tr><td>Ref. Operativa</td><td class="header-title" rowspan="3">Orden de Mantenimiento</td><td>Sucursal:</td><td>'.$e($data['estacion']).'</td></tr>'
            .'<tr><td>Proyecto de Mantenimiento</td><td>Fecha:</td><td>'.$e($data['fecha_label']).'</td></tr>'
            .'<tr><td>Código:</td><td>No. de control:</td><td>'.$e($folioControl).'</td></tr></table>'
            .'<table><tr><td colspan="2" class="station-title">DATOS DE LA ESTACIÓN DE SERVICIO</td></tr>'
            .'<tr><td width="25%">Razón social:</td><td>'.$e($data['razon_social']).'</td></tr>'
            .'<tr><td>Dirección:</td><td>'.$e($data['direccion_completa'] ?? 'S/I').'</td></tr></table>'
            .$pdfTable($affectedLabel, [], $data['afectacion'] !== '' && $data['afectacion'] !== 'S/I' ? '<tr><td class="center">'.$e($data['afectacion']).'</td></tr>' : '', 1, 'No se encontró información seleccionada.')
            .$choiceTable('Tipo de Servicio', ['Preventivo', 'Correctivo', 'Emergente'], $serviceType)
            .$choiceTable('¿La orden de trabajo se puede atender internamente?', ['Si', 'No', 'Ambas'], $workType)
            .$choiceTable('¿La orden de trabajo es de alto riesgo?', ['Si', 'No'], $riskType)
            .((int)$data['id_estacion'] !== 9 ? $pdfTable('Área', [], $areaRows, 2) : '')
            .$pdfTable('Refacciones', ['Refacción', 'Cantidad', 'Estatus'], $materialRows, 3)
            .$pdfTable('Evidencia', ['Archivo', 'Área', 'Motivo'], $evidenceRows, 3)
            .$pdfTable('Comentarios', [], '<tr><td class="center">'.$e($data['comentarios_generales'] !== '' ? $data['comentarios_generales'] : 'S/I').'</td></tr>', 1)
            .$pdfTable('Firmas', [], $signatureRows, $signatureColumnCount)
            .'</body></html>';
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->get_font('DejaVu Sans', 'normal');
        $dompdf->getCanvas()->page_text(505, 820, 'Página: {PAGE_NUM} de {PAGE_COUNT}', $font, 6, [0, 0, 0]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="orden-mantenimiento-'.$data['folio_label'].'.pdf"');
        echo $dompdf->output();
    }

    public function excel(int $id): void
    {
        if (!Service::permissions()['descargar']) {
            http_response_code(403);
            echo 'No tienes permiso para descargar esta orden.';
            return;
        }
        $data = Service::exportData($id);
        if ($data === null) {
            http_response_code(404);
            echo 'Orden no encontrada o fuera de tu alcance.';
            return;
        }
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Orden de Mtto');
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $book->getDefaultStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $book->getDefaultStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $book->getDefaultStyle()->getAlignment()->setWrapText(true);

        $styleSection = static function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '215D98']],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        };
        $styleSubHeader = static function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '749ABF']],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        };
        $section = static function (int $row, string $title) use ($sheet, $styleSection): void {
            $sheet->setCellValue('A'.$row, $title);
            $sheet->mergeCells('A'.$row.':D'.$row);
            $styleSection('A'.$row.':D'.$row);
        };

        // Legacy order header and station details.
        $sheet->fromArray([
            ['Ref. Operativa', 'Orden de Mantenimiento', 'Sucursal:', $data['estacion']],
            ['Proyecto de Mantenimiento', null, 'Fecha:', $data['fecha']],
            ['Refacturación', null, 'No. De control:', '00'.(int)$data['folio']],
        ], null, 'A1');
        $sheet->mergeCells('B1:B3');
        $sheet->setCellValue('A5', 'DATOS DE LA ESTACIÓN DE SERVICIO');
        $sheet->mergeCells('A5:D5');
        $styleSection('A5:D5');
        $sheet->setCellValue('A6', 'Razón social:');
        $sheet->setCellValue('B6', $data['razon_social']);
        $sheet->mergeCells('B6:D6');
        $sheet->setCellValue('A7', 'Dirección:');
        $sheet->setCellValue('B7', $data['direccion_completa'] ?? 'S/I');
        $sheet->mergeCells('B7:D7');

        $affectedLabel = (int)$data['id_estacion'] === 9
            ? '¿EN QUE AFECTA AL AUTOLAVADO?'
            : '¿EN QUE AFECTA A LA ESTACIÓN?';
        $section(9, $affectedLabel);
        $sheet->setCellValue('A10', $data['afectacion']);
        $sheet->mergeCells('A10:D10');

        $section(12, 'TIPO DE SERVICIO');
        $sheet->fromArray([['Preventivo', 'Correctivo', 'Emergente', null]], null, 'A13');
        $sheet->mergeCells('C13:D13');
        $sheet->setCellValue('A14', (int)$data['tipo_servicio'] === 1 ? '✔' : '');
        $sheet->setCellValue('B14', (int)$data['tipo_servicio'] === 2 ? '✔' : '');
        $sheet->setCellValue('C14', (int)$data['tipo_servicio'] === 3 ? '✔' : '');
        $sheet->mergeCells('C14:D14');

        // Keep the legacy workbook label for visual parity with its source file.
        $section(16, 'TIPO DE SERVICIO');
        $sheet->fromArray([['SI', 'NO', 'AMBAS', null]], null, 'A17');
        $sheet->mergeCells('C17:D17');
        $sheet->setCellValue('A18', (int)$data['orden_trabajo'] === 1 ? '✔' : '');
        $sheet->setCellValue('B18', (int)$data['orden_trabajo'] === 2 ? '✔' : '');
        $sheet->setCellValue('C18', (int)$data['orden_trabajo'] === 3 ? '✔' : '');
        $sheet->mergeCells('C18:D18');

        $section(20, 'LA ORDEN DE TRABAJO ES DE ALTO RIESGO');
        $sheet->setCellValue('A21', 'SI');
        $sheet->mergeCells('A21:B21');
        $sheet->setCellValue('C21', 'NO');
        $sheet->mergeCells('C21:D21');
        $sheet->setCellValue('A22', (int)$data['orden_riesgo'] === 1 ? '✔' : '');
        $sheet->mergeCells('A22:B22');
        $sheet->setCellValue('C22', (int)$data['orden_riesgo'] === 2 ? '✔' : '');
        $sheet->mergeCells('C22:D22');

        $nextRow = 24;
        if ((int)$data['id_estacion'] !== 9) {
            $section($nextRow, 'ÁREA');
            $areaRow = ++$nextRow;
            if ($data['areas'] === []) {
                $sheet->setCellValue('A'.$areaRow, 'No se encontro información');
                $sheet->mergeCells('A'.$areaRow.':D'.$areaRow);
                $nextRow = $areaRow + 2;
            } else {
                foreach ($data['areas'] as $area) {
                    $subareas = array_map(static fn($subarea): string => '('.(string)($subarea['nombre'] ?? '').')', $area['subareas'] ?? []);
                    $sheet->setCellValue('A'.$areaRow, trim((string)$area['area'].' '.implode('', $subareas)));
                    $sheet->mergeCells('A'.$areaRow.':C'.$areaRow);
                    $sheet->setCellValue('D'.$areaRow, (int)($area['estatus'] ?? 0) === 1 ? '✔' : '');
                    $areaRow++;
                }
                $nextRow = $areaRow + 1;
            }
        }

        $section($nextRow, 'REFACCIONES');
        $materialHeaderRow = $nextRow + 1;
        $sheet->fromArray([['Refacción', 'Cantidad', 'Estatus', null]], null, 'A'.$materialHeaderRow);
        $sheet->mergeCells('C'.$materialHeaderRow.':D'.$materialHeaderRow);
        $styleSubHeader('A'.$materialHeaderRow.':D'.$materialHeaderRow);
        $materialRow = $materialHeaderRow + 1;
        if ($data['materiales'] === []) {
            $sheet->setCellValue('A'.$materialRow, 'No se encontro información');
            $sheet->mergeCells('A'.$materialRow.':D'.$materialRow);
            $nextRow = $materialRow + 2;
        } else {
            foreach ($data['materiales'] as $item) {
                $sheet->setCellValue('A'.$materialRow, $item['concepto']);
                $sheet->setCellValue('B'.$materialRow, $item['cantidad']);
                $sheet->setCellValue('C'.$materialRow, $item['nota']);
                $sheet->mergeCells('C'.$materialRow.':D'.$materialRow);
                $materialRow++;
            }
            $nextRow = $materialRow + 1;
        }

        $section($nextRow, 'EVIDENCIA');
        $evidenceHeaderRow = $nextRow + 1;
        $sheet->setCellValue('A'.$evidenceHeaderRow, 'Área');
        $sheet->setCellValue('B'.$evidenceHeaderRow, 'Motivo');
        $sheet->mergeCells('B'.$evidenceHeaderRow.':D'.$evidenceHeaderRow);
        $styleSubHeader('A'.$evidenceHeaderRow.':D'.$evidenceHeaderRow);
        $evidenceRow = $evidenceHeaderRow + 1;
        if ($data['evidencias'] === []) {
            $sheet->setCellValue('A'.$evidenceRow, 'No se encontro información');
            $sheet->mergeCells('A'.$evidenceRow.':D'.$evidenceRow);
            $nextRow = $evidenceRow + 2;
        } else {
            foreach ($data['evidencias'] as $evidence) {
                $sheet->setCellValue('A'.$evidenceRow, $evidence['area']);
                $sheet->setCellValue('B'.$evidenceRow, $evidence['motivo']);
                $sheet->mergeCells('B'.$evidenceRow.':D'.$evidenceRow);
                $evidenceRow++;
            }
            $nextRow = $evidenceRow + 1;
        }

        $section($nextRow, 'COMENTARIOS');
        $sheet->setCellValue('A'.($nextRow + 1), $data['comentarios_generales'] !== '' ? $data['comentarios_generales'] : 'S/I');
        $sheet->mergeCells('A'.($nextRow + 1).':D'.($nextRow + 1));

        $lastRow = $nextRow + 1;
        $sheet->getStyle('A1:D'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:D'.$lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:D'.$lastRow)->getAlignment()->setWrapText(true);
        foreach (['A', 'B', 'C', 'D'] as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->getStyle('A1:D'.$lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
        ]);
        $writer = new Xlsx($book);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Reporte_Orden_Mantenimiento_00'.(int)$data['folio'].'.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
