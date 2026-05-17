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

        // Validar que el archivo está dentro de public/uploads (seguridad adicional)
        $publicPath = realpath(__DIR__ . '../../../public/uploads/');
        if (!$publicPath || strpos($realPath, $publicPath) !== 0) {
            http_response_code(403);
            echo 'Ubicación no permitida';
            exit;
        }

        // Sanitizar nombre de archivo para Content-Disposition
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file));

        // FORZAR DESCARGA
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($realPath));
        header('Pragma: public');

        readfile($realPath);
        exit;

    }
}