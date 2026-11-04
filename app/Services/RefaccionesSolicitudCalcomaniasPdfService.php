<?php

namespace App\Services;

/** Presentación del comprobante PDF para solicitudes de calcomanías finalizadas. */
class RefaccionesSolicitudCalcomaniasPdfService
{
    public static function generar(array $solicitud): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $logoPath = dirname(__DIR__, 2) . '/public/assets/images/logos/Logo.png';
        $logo = is_file($logoPath)
            ? '<img class="logo" src="data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath)) . '" alt="AdmonGas">'
            : '<strong class="brand">AdmonGas</strong>';
        $formatDate = static function ($value): string {
            if (!$value) return '—';
            try {
                return function_exists('formatearFecha') ? (string) formatearFecha((string) $value) : (new \DateTimeImmutable((string) $value))->format('d/m/Y H:i');
            } catch (\Throwable $e) { return '—'; }
        };
        $formatDateTime = static function ($value) use ($formatDate): string {
            $timestamp = strtotime((string) $value);
            if ($timestamp === false) return '—';
            return $formatDate($value) . ', ' . date('g:i a', $timestamp);
        };
        $details = '';
        foreach (($solicitud['detalle'] ?? []) as $index => $item) {
            $details .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $escape($item['sku_calcomania'] ?? '') . '</td><td>' . $escape($item['nombre_calcomania'] ?? '') . '</td><td class="center">' . (int) ($item['cantidad'] ?? 0) . '</td></tr>';
        }
        if ($details === '') $details = '<tr><td colspan="4" class="center muted">Sin calcomanías registradas</td></tr>';
        $firma = static function ($value, bool $autorizacion = false, $fecha = null, string $descripcion = '') use ($escape, $formatDateTime): string {
            $value = (string) $value;
            if (preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=]+$#', $value)) {
                return '<div class="signature-image"><img src="' . $escape($value) . '" alt="Firma"></div>';
            }
            if ($autorizacion && trim($value) !== '') {
                return '<div class="electronic-sign"><div class="electronic-mark">&#10003;</div><div class="electronic-note">' . $escape($descripcion !== '' ? $descripcion : 'La solicitud de calcomanías se firmó por un medio electrónico.') . '<br><b>Fecha: ' . $escape($formatDateTime($fecha)) . '</b></div></div>';
            }
            return '<div class="empty-sign">Firma no disponible</div>';
        };
        $firmaEncargado = $firma($solicitud['firma_encargado'] ?? '');
        $firmaAutorizacion = $firma($solicitud['firma_autorizacion'] ?? '', true, $solicitud['firmado_autorizacion_en'] ?? null, (string) ($solicitud['firma_autorizacion_descripcion'] ?? ''));
        $statusLabel = [0 => 'Pendiente', 1 => 'En proceso', 2 => 'Finalizado'][(int) ($solicitud['estado'] ?? -1)] ?? 'Desconocido';

        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
            @page{margin:32px 40px}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;color:#24344d;font-size:10px;line-height:1.45}
            .head{width:100%;border-collapse:collapse;border-bottom:3px solid #315fce;margin-bottom:20px}.head td{border:0;padding:0 0 12px;vertical-align:middle}.logo{width:155px;height:auto}.brand{font-size:24px;color:#244e88}.title{text-align:right}h1{font-size:17px;color:#244e88;margin:0 0 4px}.muted{color:#64748b}.folio{font-weight:bold;color:#244e88}
            .details{width:100%;border-collapse:collapse;margin-bottom:18px}.details td{border:0;padding:7px 6px;border-bottom:1px solid #e3eaf4}.details .label{width:20%;font-weight:bold}.block-title{background:#6381ff;color:#fff;padding:8px 10px;font-size:11px;font-weight:bold;border-radius:4px 4px 0 0;margin-top:14px}
            table.items{width:100%;border-collapse:collapse}table.items th,table.items td{padding:7px;border:1px solid #d6e0ef}table.items th{background:#f5f8fc;text-align:left}.center{text-align:center}
            .signatures{width:100%;table-layout:fixed;border-collapse:collapse;margin-top:50px}.signatures td{width:50%;padding:8px 14px;vertical-align:top;border:1px solid #d7dce4;text-align:center}.signer-name{min-height:20px;color:#64748b}.signature-image{height:105px;padding:8px 0;border-bottom:1px solid #9ca3af}.signature-image img{width:200px;max-height:90px;object-fit:contain}.empty-sign{height:105px;padding:40px 8px;border-bottom:1px solid #9ca3af;color:#94a3b8}.electronic-sign{height:105px;padding:8px 10px;border-bottom:1px solid #9ca3af;color:#475569;font-size:9px}.electronic-mark{height:43px;color:#6381ff;font-size:34px;line-height:40px;font-weight:bold}.electronic-note{padding-top:5px}.sign-role{margin-top:10px;color:#334155;font-weight:bold}
            </style></head><body><table class="head"><tr><td>' . $logo . '</td><td class="title"><h1>Solicitud de calcomanías</h1><div class="muted">Almacén — Refacciones</div><div class="folio">Folio: #00' . (int) ($solicitud['id'] ?? 0) . '</div></td></tr></table>
            <table class="details"><tr><td class="label">Fecha y hora:</td><td>' . $escape($formatDate($solicitud['fecha_hora'] ?? null)) . '</td><td class="label">Estado:</td><td>' . $escape($statusLabel) . '</td></tr><tr><td class="label">Ubicación:</td><td>' . $escape($solicitud['nombre_ubicacion'] ?? '—') . '</td><td class="label">Solicitante:</td><td>' . $escape($solicitud['nombre_personal'] ?? '—') . '</td></tr><tr><td class="label">Puesto:</td><td colspan="3">' . $escape($solicitud['puesto'] ?? '—') . '</td></tr><tr><td class="label">Observaciones:</td><td colspan="3">' . nl2br($escape(trim((string) ($solicitud['observaciones'] ?? '')) ?: 'Sin información')) . '</td></tr></table>
            <div class="block-title">Calcomanías solicitadas</div><table class="items"><thead><tr><th class="center">#</th><th>SKU</th><th>Calcomanía</th><th class="center">Piezas</th></tr></thead><tbody>' . $details . '</tbody></table>
            <table class="signatures"><tbody><tr><td><div class="signer-name">' . $escape($solicitud['nombre_personal'] ?? 'Sin nombre registrado') . '</div>' . $firmaEncargado . '<div class="sign-role">NOMBRE Y FIRMA DEL ENCARGADO</div></td><td><div class="signer-name">' . $escape($solicitud['nombre_usuario_autorizacion'] ?? 'Sin nombre registrado') . '</div>' . $firmaAutorizacion . '<div class="sign-role">NOMBRE Y FIRMA DE AUTORIZACIÓN</div></td></tr></tbody></table>
            </body></html>';
    }
}
