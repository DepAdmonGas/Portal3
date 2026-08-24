<?php

namespace App\Controllers;

class DownloadController
{

    public function download()
    {

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
    'bitacora-aditivo'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/bitacora-aditivo/',
    'analisis-riesgo'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/analisis-riesgo/',
    'solicitud-gafetes'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-gafetes/',
    'solicitud-tarjetas'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/',
    'procedimientos-actividades-tecnicas'  => dirname(__DIR__, 2) . '/public/uploads/archivos/actividades-tecnicas/',
    'procedimientos-visita-estacion'       => dirname(__DIR__, 2) . '/public/uploads/archivos/visita-estacion/',
    'empresa'                              => dirname(__DIR__, 2) . '/public/uploads/archivos/empresa/',
    'poliza-seguro'                        => dirname(__DIR__, 2) . '/public/uploads/archivos/poliza-seguro/',
    'requisitos-legales'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/reuisitos-legales/',
    'encuestas'                            => dirname(__DIR__, 2) . '/public/uploads/archivos/encuestas/',
    'representante-tecnico'                => dirname(__DIR__, 2) . '/public/uploads/archivos/representante-tecnico/',
    'manual'                               => dirname(__DIR__, 2) . '/public/uploads/archivos/manuales/',
    'comprobantes-clientes'                => dirname(__DIR__, 2) . '/public/uploads/archivos/clientes/',
    'documentos-ventas'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/',
    'control-volumetrico'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/',
    'aceites-documentos'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/aceites-documentos/',
    'aceites-facturas'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/aceites-facturas/',
    'aceites-diferencias'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/aceites-diferencias/',
    'monedero-documentos'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/',
    'monedero-lista-documentos'            => dirname(__DIR__, 2) . '/public/uploads/archivos/resumen-monederos-documentos/',
    'embarques'                            => dirname(__DIR__, 2) . '/public/uploads/archivos/embarques/',
    'solicitud-cheque'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-cheque/',
    'ingresos-facturacion'                 => dirname(__DIR__, 2) . '/public/uploads/archivos/ingresos-facturacion/',
    'contratos'                            => dirname(__DIR__, 2) . '/public/uploads/archivos/contratos/',
    'estimulo-fiscal'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/estimulo-fiscal/',
    'comparativo-xml'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/comparativo-xml/',
    'seguros-incidencias'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/incidencias-poliza-es/',
    'seguros-polizas'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/poliza-estacion/',
    'aclaracion-voucher'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/aclaracion-voucher/',
    'solicitud-vales'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-vales/',
    'factura-monedero'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/factura-monedero/',
    'organigrama'                          => dirname(__DIR__, 2) . '/public/uploads/archivos/organigrama/',
    'organigrama-documentos'               => dirname(__DIR__, 2) . '/public/uploads/archivos/organigrama-documentos/',
    'docs-personal-requisicion'            => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/requisicion/',
    'docs-personal-curriculum'             => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/curriculum/',
    'docs-personal-ine'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/ine/',
    'docs-personal-acta-nacimiento'        => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/acta_nacimiento/',
    'docs-personal-c-domicilio'            => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/comprobante_domicilio/',
    'docs-personal-nss'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/nss/',
    'docs-personal-c-estudios'             => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/comprobante_estudios/',
    'docs-personal-c-recomendacion'        => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/cartas_recomendacion/',
    'docs-personal-curp'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/curp/',
    'docs-personal-a-infonavit'            => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/acta_infonavit/',
    'docs-personal-rfc'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/rfc/',
    'docs-personal-c-antecedentes'         => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/carta_antecedentes/',
    'docs-personal-contrato'               => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/contrato/',
    'docs-personal-documentos'             => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/',
    'docs-personal-baja'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/solicitud-baja/',
    'docs-personal-incidencias'            => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/incidencias/',
    'dia-doble-firma'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/dia-doble-firma/',
    'lista-formatos'                       => dirname(__DIR__, 2) . '/public/uploads/archivos/lista-formatos/',
    'formatos-alta'                        => dirname(__DIR__, 2) . '/public/uploads/archivos/formatos/alta/',
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
        $publicPath = realpath(dirname(__DIR__, 2) . '/public/');
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
