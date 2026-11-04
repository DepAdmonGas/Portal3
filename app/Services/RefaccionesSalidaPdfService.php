<?php

namespace App\Services;

/** Presentación PDF del acuse para una salida finalizada de Refacciones. */
class RefaccionesSalidaPdfService
{
    public static function generar(array $salida): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $logoPath = dirname(__DIR__, 2) . '/public/assets/images/logos/Logo.png';
        $logo = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
            : '';
        $logoMarkup = $logo !== ''
            ? '<img src="' . $logo . '" alt="AdmonGas" class="logo">'
            : '<div class="brand">AdmonGas</div>';

        $fecha = self::fecha($salida['fecha'] ?? null);
        $fechaRetorno = self::fecha($salida['fecha_retorno'] ?? null);
        $estadoRetorno = mb_strtolower(trim((string) ($salida['estado_salida'] ?? 'prestado'))) === 'devuelto'
            ? 'Devuelto a la estación de origen'
            : 'En préstamo';
        $unidad = trim((string) ($salida['unidad_medida'] ?? ''));
        $cantidad = rtrim(rtrim(number_format((float) ($salida['cantidad'] ?? 0), 3, '.', ','), '0'), '.');
        if ($unidad !== '') $cantidad .= ' ' . $unidad;

        $leftSignature = self::firma($salida['firma_elabora'] ?? '', $escape);
        $rightSignature = self::firma($salida['firma_recibe'] ?? '', $escape);
        $elaboraName = trim((string) ($salida['nombre_elabora'] ?? $salida['nombre_entrega'] ?? ''));
        $recibeName = trim((string) ($salida['nombre_recibe'] ?? $salida['acuse_nombre_recibe'] ?? ''));
        $elaboraDate = self::fecha($salida['fecha_firma_elabora'] ?? null);
        $recibeDate = self::fecha($salida['fecha_firma_recibe'] ?? null);

        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
            @page{margin:30px 42px}*{box-sizing:border-box}body{margin:0;color:#24344d;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.45}
            .head{width:100%;border-collapse:collapse;border-bottom:3px solid #315fce;margin-bottom:18px}.head td{border:0;padding:0 0 12px;vertical-align:middle}
            .brand-cell{width:34%}.logo{width:170px;height:auto}.brand{font-size:24px;font-weight:bold;color:#244e88}.title-cell{text-align:right}
            h1{font-size:18px;color:#244e88;margin:0 0 4px}.subtitle{color:#64748b;font-size:10px}.folio{display:inline-block;margin-top:8px;padding:5px 10px;border:1px solid #b7c7e2;border-radius:4px;color:#244e88;font-weight:bold}
            .details{width:100%;border-collapse:collapse;margin-top:9px}.details th,.details td{border:0;padding:6px 8px;vertical-align:top}.details th{width:20%;text-align:left;color:#24344d;font-weight:bold}.details td{border-bottom:1px solid #e3eaf4}
            .signatures{width:100%;border-collapse:separate;border-spacing:18px 0;margin-top:26px}.signatures td{width:50%;border:1px solid #d7e0ef;border-radius:5px;padding:12px;text-align:center;vertical-align:top}
            .sig-title{font-weight:bold;color:#244e88;margin-bottom:7px}.sig-image{height:82px;display:block;margin:0 auto 5px;max-width:220px;object-fit:contain}.sig-empty{height:82px;color:#94a3b8;padding-top:27px;border-bottom:1px solid #aab6c7;margin-bottom:5px}
            .sig-name{font-weight:bold}.sig-date{color:#64748b;font-size:9px;margin-top:2px}.foot{margin-top:18px;padding-top:7px;border-top:1px solid #d7e0ef;color:#64748b;font-size:8px;text-align:center}
            </style></head><body><table class="head"><tr><td class="brand-cell">' . $logoMarkup . '</td><td class="title-cell"><h1>Acuse de salida de almacén</h1><div class="subtitle">Almacén Central — Red de Estaciones de Servicio</div><div class="folio">Folio: ' . $escape($salida['folio'] ?? '') . '</div></td></tr></table>
            <table class="details"><tbody>
                <tr><th>Fecha de salida:</th><td>' . $escape($fecha) . '</td><th>Estado:</th><td>Finalizado</td></tr>
                <tr><th>Producto:</th><td colspan="3">' . $escape($salida['producto'] ?? '') . (!empty($salida['sku']) ? ' <span class="subtitle">(' . $escape($salida['sku']) . ')</span>' : '') . '</td></tr>
                <tr><th>Estación de origen:</th><td>' . $escape($salida['origen'] ?? '') . '</td><th>Cantidad:</th><td>' . $escape($cantidad) . '</td></tr>
                <tr><th>Estación de destino:</th><td>' . $escape($salida['destino'] ?? '') . '</td><th>Motivo:</th><td>' . $escape($salida['motivo'] ?? '—') . '</td></tr>
                <tr><th>Fecha de retorno:</th><td>' . $escape($fechaRetorno ?: 'Sin asignar') . '</td><th>Estado del retorno:</th><td>' . $escape($estadoRetorno) . '</td></tr>
                <tr><th>Notas:</th><td colspan="3">' . $escape(trim((string) ($salida['notas'] ?? '')) !== '' ? $salida['notas'] : 'Sin información') . '</td></tr>
            </tbody></table>
            <table class="signatures"><tr>
                <td><div class="sig-title">Entregó · Firma de quien elabora</div>' . $leftSignature . '<div class="sig-name">' . $escape($elaboraName !== '' ? $elaboraName : 'Sin nombre registrado') . '</div>' . ($elaboraDate !== '' ? '<div class="sig-date">Fecha de firma: ' . $escape($elaboraDate) . '</div>' : '') . '</td>
                <td><div class="sig-title">Recibió · Firma por la estación</div>' . $rightSignature . '<div class="sig-name">' . $escape($recibeName !== '' ? $recibeName : 'Sin nombre registrado') . '</div>' . ($recibeDate !== '' ? '<div class="sig-date">Fecha de firma: ' . $escape($recibeDate) . '</div>' : '') . '</td>
            </tr></table><div class="foot">Acuse generado por el módulo de Almacén · Refacciones</div></body></html>';
    }

    public static function nombreArchivo(string $folio): string
    {
        $safeFolio = preg_replace('/[^A-Za-z0-9_-]/', '-', $folio) ?: 'salida';
        return 'Acuse-' . trim($safeFolio, '-') . '.pdf';
    }

    private static function firma($relativePath, callable $escape): string
    {
        $relativePath = (string) $relativePath;
        if ($relativePath === '' || !preg_match('#^uploads/refacciones-firmas/[a-f0-9]{20,64}\.png$#i', $relativePath)) {
            return '<div class="sig-empty">Firma no disponible</div>';
        }
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $file = realpath(dirname(__DIR__, 2) . '/public/' . $relativePath);
        if ($publicRoot === false || $file === false || !str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return '<div class="sig-empty">Firma no disponible</div>';
        }
        return '<img class="sig-image" src="data:image/png;base64,' . base64_encode((string) file_get_contents($file)) . '" alt="Firma digital">';
    }

    private static function fecha($value): string
    {
        if (!$value) return '';
        try {
            return function_exists('formatearFecha') ? (string) formatearFecha($value) : (new \DateTimeImmutable((string) $value))->format('d/m/Y');
        } catch (\Throwable $exception) {
            return '';
        }
    }
}
