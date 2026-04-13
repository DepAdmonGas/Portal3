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
            'requisitos-legales' => __DIR__ . '../../../public/uploads/archivos/reuisitos-legales/'

        ];

        if (!isset($rutas[$tipo])) {
            http_response_code(403);
            echo 'Tipo no permitido';
            exit;
        }

        $ruta = $rutas[$tipo] . $file;

        //------ ERROR (configuracion) ----------//
        if (!file_exists($ruta)) {
            header("Location: /404");
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