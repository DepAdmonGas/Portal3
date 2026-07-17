<?php

namespace App\Controllers;

class DownloadController{
 
    public function download(){

        $tipo  = $_GET['tipo'] ?? null;
        $file  = $_GET['file'] ?? null;

        if (!$tipo || !$file) {
            header("Location: /404");
            exit;
        }

        // LIMPIAR NOMBRE
        $file = basename($file);

        // MAPA DE CARPETAS (CONTROLADO)
        $rutas = [
            'bitacora-aditivo' => __DIR__ . '../../../public/uploads/archivos/bitacora-aditivo/',
            'analisis-riesgo' => __DIR__ . '../../../public/uploads/archivos/analisis-riesgo/',
            'solicitud-gafetes' => __DIR__ . '../../../public/uploads/archivos/solicitud-gafetes/',
            'solicitud-tarjetas' => __DIR__ . '../../../public/uploads/archivos/solicitud-tarjetas/',
            'procedimientos-actividades-tecnicas' => __DIR__ . '../../../public/uploads/archivos/actividades-tecnicas/',
            'procedimientos-visita-estacion' => __DIR__ . '../../../public/uploads/archivos/visita-estacion/',
            'empresa' => __DIR__ . '../../../public/uploads/archivos/empresa/',
            'poliza-seguro' => __DIR__ . '../../../public/uploads/archivos/poliza-seguro/',
            'requisitos-legales' => __DIR__ . '../../../public/uploads/archivos/reuisitos-legales/',
            'encuestas' => __DIR__ . '../../../public/uploads/archivos/encuestas/',
            'representante-tecnico' => __DIR__ . '../../../public/uploads/archivos/representante-tecnico/',
            'manual' => __DIR__ . '../../../public/uploads/archivos/manuales/',
            'comprobantes-clientes' => __DIR__ . '../../../public/archivos/',
            'documentos-ventas'              => __DIR__ . '../../../public/uploads/archivos/',
            'control-volumetrico'          => __DIR__ . '../../../public/uploads/archivos/',
            'aceites-documentos'           => __DIR__ . '../../../public/uploads/archivos/aceites-documentos/',
            'aceites-facturas'             => __DIR__ . '../../../public/uploads/archivos/aceites-facturas/',
            'aceites-diferencias'          => __DIR__ . '../../../public/uploads/archivos/aceites-diferencias/',
            'monedero-documentos'          => __DIR__ . '../../../public/uploads/archivos/',
            'monedero-lista-documentos'    => __DIR__ . '../../../public/uploads/archivos/resumen-monederos-documentos/',
            'embarques'                    => __DIR__ . '../../../public/uploads/archivos/embarques/',
            'solicitud-cheque'             => __DIR__ . '../../../public/uploads/archivos/solicitud-cheque/',
            'ingresos-facturacion'         => __DIR__ . '../../../public/uploads/archivos/ingresos-facturacion/',
'contratos'                    => __DIR__ . '../../../public/uploads/archivos/contratos/',
            'estimulo-fiscal'                => __DIR__ . '../../../public/uploads/archivos/estimulo-fiscal/',
            'comparativo-xml'                => __DIR__ . '../../../public/uploads/archivos/comparativo-xml/',
            'seguros-incidencias'            => __DIR__ . '../../../public/uploads/archivos/incidencias-poliza-es/',
            'seguros-polizas'                => __DIR__ . '../../../public/uploads/archivos/poliza-estacion/',
            'aclaracion-voucher'             => __DIR__ . '../../../public/uploads/archivos/aclaracion-voucher/',
            'solicitud-vales'                => __DIR__ . '../../../public/uploads/archivos/solicitud-vales/',
        ];

        if (!isset($rutas[$tipo])) {
            http_response_code(403);
            echo 'Tipo no permitido';
            exit;
        }

        $ruta = $rutas[$tipo] . $file;

        // ============================================================
        // SECURITY: Validación de path traversal y Directory Traversal
        // ============================================================

        // Obtener path real del archivo solicitado
        $realPath = realpath($ruta);

        // Obtener path real del directorio base
        $basePath = realpath($rutas[$tipo]);

        // Verificar que el archivo existe y está dentro del directorio esperado
        if (!$realPath || !$basePath || strpos($realPath, $basePath) !== 0) {
            http_response_code(403);
            echo 'Archivo no permitido';
            exit;
        }

        // Validar que el archivo está dentro de public/ (seguridad adicional)
        $publicPath = realpath(__DIR__ . '../../../public/');
        if (!$publicPath || strpos($realPath, $publicPath) !== 0) {
            http_response_code(403);
            echo 'Ubicación no permitida';
            exit;
        }

        // Sanitizar nombre de archivo para Content-Disposition
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file));

        // ?view=1 para visualización inline (PDF/imágenes en iframe)
        $viewMode = isset($_GET['view']);

        if ($viewMode) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $contentTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            if (isset($contentTypes[$ext])) {
                header('Content-Type: ' . $contentTypes[$ext]);
                header('Content-Disposition: inline; filename="' . $safeFilename . '"');
            } else {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            }
        } else {
            // FORZAR DESCARGA
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            header('Pragma: public');
        }

        header('Content-Length: ' . filesize($realPath));

        readfile($realPath);
        exit;

    }
}