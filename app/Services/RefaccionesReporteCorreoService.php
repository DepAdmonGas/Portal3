<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Genera y distribuye los reportes de almacén de Refacciones. */
class RefaccionesReporteCorreoService
{
    private const PERIODOS = [
        'mensual' => 'mensual',
        'semestral' => 'semestral',
        'anual' => 'anual',
    ];

    /** @return array{success:bool,message:string,enviados:int,fallidos:int} */
    public static function enviar(string $periodo, int $year, int $month, bool $general = false): array
    {
        if (!isset(self::PERIODOS[$periodo]) || $year < 2024 || $year > (int) date('Y') || $month < 1 || $month > 12) {
            return ['success' => false, 'message' => 'El período del reporte no es válido.', 'enviados' => 0, 'fallidos' => 0];
        }

        $report = $general
            ? RefaccionesService::getReporteGeneral($periodo, $year, $month)
            : RefaccionesService::getReporte($periodo, $year, $month);
        $pdf = self::generarPdf($report);
        if ($pdf === '') {
            return ['success' => false, 'message' => 'No se pudo generar el PDF del reporte.', 'enviados' => 0, 'fallidos' => 0];
        }

        $recipients = DB::table('tb_usuarios')
            ->where('id_puesto', 13)
            ->where('estatus', 0)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get(['email', 'nombre']);
        $emails = [];
        foreach ($recipients as $recipient) {
            $email = trim((string) $recipient->email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $emails[$email] = trim((string) ($recipient->nombre ?? ''));
        }
        if ($emails === []) {
            return ['success' => false, 'message' => 'No hay destinatarios activos con correo válido para el puesto 13.', 'enviados' => 0, 'fallidos' => 0];
        }

        $title = self::titulo($report);
        $subject = $title . ' — ' . (string) ($report['label'] ?? '');
        $fileName = self::nombreArchivo($report);
        $emailService = new EmailService();
        $sent = 0;
        $failed = 0;
        foreach ($emails as $email => $name) {
            $greeting = $name !== '' ? 'Hola ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',' : 'Hola,';
            $body = '<p>' . $greeting . '</p><p>Adjuntamos el <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                . '</strong> correspondiente al período ' . htmlspecialchars((string) ($report['label'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '.</p><p>El documento incluye el resumen de entradas y salidas, así como el detalle por producto.</p><p>Saludos,<br>Almacén AdmonGas</p>';
            $altBody = $greeting . "\n\nAdjuntamos el " . $title . ' correspondiente al período ' . (string) ($report['label'] ?? '')
                . ". El documento incluye el resumen de entradas y salidas, y el detalle por producto.\n\nAlmacén AdmonGas";
            if ($emailService->sendWithPdfContent($email, $subject, $body, $pdf, $fileName, $altBody)) $sent++;
            else $failed++;
        }

        if ($sent === 0) return ['success' => false, 'message' => 'No se pudo enviar el reporte a los destinatarios.', 'enviados' => 0, 'fallidos' => $failed];
        if ($failed > 0) return ['success' => false, 'message' => "El reporte se envió a {$sent} destinatarios; {$failed} envíos fallaron.", 'enviados' => $sent, 'fallidos' => $failed];
        return ['success' => true, 'message' => "El reporte se envió a {$sent} destinatarios.", 'enviados' => $sent, 'fallidos' => 0];
    }

    /** Primer día del mes: distribución del mes calendario anterior. */
    public static function enviarReporteMensualAnterior(?\DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new \DateTimeImmutable('now');
        if ($hoy->format('j') !== '1') {
            return ['success' => false, 'message' => 'La tarea mensual sólo se ejecuta el primer día del mes.', 'enviados' => 0, 'fallidos' => 0];
        }
        $mesAnterior = $hoy->modify('first day of last month');
        return self::enviar('mensual', (int) $mesAnterior->format('Y'), (int) $mesAnterior->format('n'), true);
    }

    private static function generarPdf(array $report): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $quantity = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
        $title = self::titulo($report);
        $logoPath = dirname(__DIR__, 2) . '/public/assets/images/logos/Logo.png';
        $logo = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
            : '';
        $rows = '';
        foreach (($report['detalle'] ?? []) as $row) {
            $rows .= '<tr><td>' . $escape($row['sku'] ?? '') . '</td><td>' . $escape($row['nombre'] ?? '') . '</td><td class="num">'
                . $quantity($row['entradas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($row['valor_entradas'] ?? 0), 2) . '</td><td class="num">'
                . $quantity($row['salidas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($row['valor_salidas'] ?? 0), 2) . '</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="6" class="empty">Sin movimientos en este período.</td></tr>';
        $period = $escape($report['label'] ?? '');
        $logoMarkup = $logo !== ''
            ? '<img src="' . $logo . '" alt="AdmonGas" style="width:170px; height:auto;">'
            : '<strong class="brand">AdmonGas</strong>';
        $html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
            @page{margin:28px 34px}*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#212529;font-size:10px;line-height:1.35}
            .document-head{width:100%;border-collapse:collapse;border-bottom:3px solid #315f9b;margin-bottom:18px}
            .document-head td{border:0;padding:0 0 12px;vertical-align:middle}.brand-cell{width:34%}.title-cell{text-align:right}
            .brand{font-size:24px;color:#244e88}.document-title{font-size:18px;font-weight:bold;color:#244e88;margin:0 0 5px}
            .period{font-size:10px;color:#53657e}h2{font-size:13px;color:#244e88;margin:18px 0 8px}
            table.data{width:100%;border-collapse:collapse;margin-bottom:12px}table.data th{background:#f2f2f2;color:#24344d;text-align:left;font-weight:bold}
            table.data th,table.data td{border:1px solid #dee2e6;padding:6px;vertical-align:middle}
            table.data th.num,table.data td.num{text-align:right}
            .summary td:first-child{width:58%}.num{text-align:right;white-space:nowrap}.total{font-weight:bold;background:#e9ecef}
            .empty{text-align:center;color:#64748b;padding:18px}.foot{margin-top:18px;padding-top:7px;border-top:1px solid #dee2e6;color:#6c757d;font-size:8px}
            </style></head><body><table class="document-head"><tr><td class="brand-cell">' . $logoMarkup . '</td><td class="title-cell"><div class="document-title">' . $escape($title) . '</div><div class="period">Período: ' . $period . '</div></td></tr></table>
            <h2>Resumen</h2><table class="data summary"><thead><tr><th>Concepto</th><th class="num">Piezas</th><th class="num">Valor</th></tr></thead><tbody>
            <tr><td>Entradas del período</td><td class="num">' . $quantity($report['entradas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($report['valor_entradas'] ?? 0), 2) . '</td></tr>
            <tr><td>Salidas / consumo</td><td class="num">' . $quantity($report['salidas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($report['valor_salidas'] ?? 0), 2) . '</td></tr>
            <tr class="total"><td>Total de movimientos</td><td class="num">' . $quantity((float) ($report['entradas'] ?? 0) + (float) ($report['salidas'] ?? 0)) . '</td><td class="num">$' . number_format((float) ($report['valor_entradas'] ?? 0) + (float) ($report['valor_salidas'] ?? 0), 2) . '</td></tr>
            </tbody></table><h2>Detalle por producto</h2><table class="data"><thead><tr><th>SKU</th><th>Producto</th><th class="num">Entradas</th><th class="num">Valor entradas</th><th class="num">Salidas</th><th class="num">Valor consumo</th></tr></thead><tbody>' . $rows
            . '<tr class="total"><td colspan="2">Total</td><td class="num">' . $quantity($report['entradas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($report['valor_entradas'] ?? 0), 2)
            . '</td><td class="num">' . $quantity($report['salidas'] ?? 0) . '</td><td class="num">$' . number_format((float) ($report['valor_salidas'] ?? 0), 2)
            . '</td></tr></tbody></table><div class="foot">Reporte generado por el módulo de Almacén · Refacciones</div></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return $dompdf->output();
    }

    private static function titulo(array $report): string
    {
        $year = (int) ($report['year'] ?? date('Y'));
        return match ($report['periodo'] ?? 'mensual') {
            'anual' => 'Reporte anual de almacén — ' . $year,
            'semestral' => 'Reporte semestral de almacén — ' . ($report['semestre'] === 2 ? 'julio–diciembre ' : 'enero–junio ') . $year,
            default => 'Reporte mensual de almacén — ' . self::nombreMes((int) ($report['month'] ?? 1)) . ' ' . $year,
        };
    }

    private static function nombreMes(int $month): string
    {
        return [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][$month] ?? 'enero';
    }

    private static function nombreArchivo(array $report): string
    {
        $year = (int) ($report['year'] ?? date('Y'));
        $periodo = (string) ($report['periodo'] ?? 'mensual');
        if ($periodo === 'mensual') return sprintf('Reporte-almacen-%04d-%02d.pdf', $year, (int) ($report['month'] ?? 1));
        if ($periodo === 'semestral') return sprintf('Reporte-almacen-%04d-semestre-%d.pdf', $year, (int) ($report['semestre'] ?? 1));
        return sprintf('Reporte-almacen-%04d-anual.pdf', $year);
    }
}
