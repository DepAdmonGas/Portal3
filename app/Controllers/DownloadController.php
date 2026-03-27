<?php

namespace App\Controllers;

class DownloadController{

    public function download(){

        $tipo  = $_GET['tipo'] ?? null;
        $file  = $_GET['file'] ?? null;

        if (!$tipo || !$file) {
            http_response_code(400);
            echo 'Parámetros inválidos';
            exit;
        }

        // LIMPIAR NOMBRE
        $file = basename($file);

        // MAPA DE CARPETAS (CONTROLADO)
        $rutas = [
            'basico' => __DIR__ . '../../../public/uploads/archivos/'
        ];

        if (!isset($rutas[$tipo])) {
            http_response_code(403);
            echo 'Tipo no permitido';
            exit;
        }

        $ruta = $rutas[$tipo] . $file;

        if (!file_exists($ruta)) {
            http_response_code(404);
            echo 'Archivo no encontrado';
            exit;
        }

        // FORZAR DESCARGA
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Pragma: public');

        readfile($ruta);
        exit;

    }
}