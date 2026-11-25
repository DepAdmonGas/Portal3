<?php

namespace App\Controllers;

use App\Services\SensitiveDownloadAuthorizationService;
use App\Services\ModuloService;

class DownloadController
{
    public function download()
    {
        $tipo = $_GET['tipo'] ?? null;
        $file = $_GET['file'] ?? null;

        if (!$tipo || !$file) {
            header("Location: /404");
            exit;
        }

        if (str_starts_with((string) $tipo, 'sasisopa-')
            && !ModuloService::validaPermiso('sasisopa', 'descargar')) {
            http_response_code(404);
            exit;
        }

        // LIMPIAR NOMBRE
        $file = basename(str_replace('\\', '/', (string) $file));

        // MAPA DE CARPETAS (CONTROLADO)
        $rutas = [
            'pedido-papeleria'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'pedido-pinturas'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'pedido-articulos-limpieza'             => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'bitacora-aditivo'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/bitacora-aditivo/',
            'analisis-riesgo'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/analisis-riesgo/',
            'solicitud-gafetes'                    => [ dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-gafetes/', dirname(__DIR__, 2) . '/public/uploads/archivos/gafetes-solicitados/', dirname(__DIR__, 2) . '/public/uploads/archivos/servicios/imagenes-gafetes/', dirname(__DIR__, 2) . '/public/uploads/archivos/Imagen/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'solicitud-tarjetas'                   => [ dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/', dirname(__DIR__, 2) . '/public/uploads/archivos/servicios/imagenes/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'procedimientos-actividades-tecnicas'  => dirname(__DIR__, 2) . '/public/uploads/archivos/actividades-tecnicas/',
            'procedimientos-visita-estacion'       => dirname(__DIR__, 2) . '/public/uploads/archivos/visita-estacion/',
            'empresa'                              => dirname(__DIR__, 2) . '/public/uploads/archivos/empresa/',
            'poliza-seguro'                        => dirname(__DIR__, 2) . '/public/uploads/archivos/poliza-seguro/',
            'requisitos-legales'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/reuisitos-legales/',
            'encuestas'                            => dirname(__DIR__, 2) . '/public/uploads/archivos/encuestas/',
            'representante-tecnico'                => dirname(__DIR__, 2) . '/public/uploads/archivos/representante-tecnico/',
            'manual'                               => dirname(__DIR__, 2) . '/public/uploads/archivos/manuales/',
            'comprobantes-clientes'                => [ dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/archivos/clientes/', ],
            'documentos-ventas'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'ventas-firmas'                        => dirname(__DIR__, 2) . '/public/uploads/archivos/ventas-firmas/',
            'ventas-firmas-assets-legado'           => dirname(__DIR__, 2) . '/public/assets/img/firmas/',
            'ventas-firmas-uploads-legado'           => dirname(__DIR__, 2) . '/public/uploads/firmas/',
            'ventas-firmas-imgs-legado'              => dirname(__DIR__, 2) . '/imgs/firma/',
            'ventas-firmas-public-imgs-legado'       => dirname(__DIR__, 2) . '/public/imgs/firma/',
            'control-volumetrico'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'aceites-documentos'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'aceites-facturas'                     => [ dirname(__DIR__, 2) . '/public/uploads/archivos/aceites-facturas/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'aceites-diferencias'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'monedero-documentos'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'monedero-lista-documentos'            => dirname(__DIR__, 2) . '/public/uploads/archivos/resumen-monederos-documentos/',
            'embarques'                            => [ dirname(__DIR__, 2) . '/public/uploads/archivos/embarques/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'solicitud-cheque'                     => [ dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-cheque/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'solicitud-cheque-firmas'               => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-cheque-firmas/',
            'solicitud-cheque-firmas-raiz'          => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'solicitud-cheque-firmas-legado'        => dirname(__DIR__, 2) . '/public/uploads/firmas/solicitud-cheque/',
            'solicitud-cheque-firmas-uploads'       => dirname(__DIR__, 2) . '/public/uploads/firmas/',
            'solicitud-cheque-firmas-imgs'          => dirname(__DIR__, 2) . '/public/imgs/firma/',
            'solicitud-cheque-firmas-imgs-legado'   => dirname(__DIR__, 2) . '/imgs/firma/',
            'ingresos-facturacion'                 => dirname(__DIR__, 2) . '/public/uploads/archivos/ingresos-facturacion/',
            'contratos'                            => dirname(__DIR__, 2) . '/public/uploads/archivos/contratos/',
            'contratos-legado'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/contratos-es/',
            'estimulo-fiscal'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/estimulo-fiscal/',
            'comparativo-xml'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/comparativo-xml/',
            'seguros-incidencias'                  => dirname(__DIR__, 2) . '/public/uploads/archivos/incidencias-poliza-es/',
            'seguros-polizas'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/poliza-estacion/',
            'aclaracion-voucher'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/aclaracion-baucher/',
            'solicitud-vales'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-vales/',
            'solicitud-vales-legado'               => dirname(__DIR__, 2) . '/public/uploads/archivos/vales/',
            'lista-negra'                          => [ dirname(__DIR__, 2) . '/public/uploads/archivos/lista-negra/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'bitacora-rrhh'                        => [ dirname(__DIR__, 2) . '/public/uploads/archivos/bitacora-rrhh/', dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/archivos/bitacoraRRHH/', ],
            'factura-monedero'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/facturas-monederos/',
            'organigrama'                          => [ dirname(__DIR__, 2) . '/public/uploads/archivos/organigrama/', dirname(__DIR__, 2) . '/public/uploads/archivos/organigrama/organigramas/', ],
            'organigrama-documentos'               => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos/',
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
            'docs-personal-incidencias'            => dirname(__DIR__, 2) . '/public/uploads/archivos/incidencias/',
            'dia-doble-firma'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/dia-doble-firma/',
            'permisos-firma'                       => [ dirname(__DIR__, 2) . '/public/uploads/archivos/permisos-firma/', dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/firmas/permisos/', dirname(__DIR__, 2) . '/public/uploads/firmas/', dirname(__DIR__, 2) . '/public/imgs/firma/', dirname(__DIR__, 2) . '/imgs/firma/', ],
            'lista-formatos'                       => dirname(__DIR__, 2) . '/public/uploads/archivos/lista-formatos/',
            'formatos-alta'                        => [ dirname(__DIR__, 2) . '/public/uploads/archivos/formatos/alta/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'formatos-firmas'                      => [ dirname(__DIR__, 2) . '/public/uploads/firmas/formatos/', dirname(__DIR__, 2) . '/public/uploads/firmas/', dirname(__DIR__, 2) . '/public/uploads/archivos/formatos/firmas/', dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/imgs/firma/', dirname(__DIR__, 2) . '/imgs/firma/', ],
            'refacciones-firmas'                    => [ dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones-firmas/', dirname(__DIR__, 2) . '/public/uploads/refacciones-firmas/', ],
            'formatos-alta-curriculum'             => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/curriculum/',
            'formatos-alta-ine'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/ine/',
            'formatos-alta-acta-nacimiento'        => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/acta_nacimiento/',
            'formatos-alta-nss'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/nss/',
            'formatos-alta-domicilio'              => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/comprobante_domicilio/',
            'formatos-alta-estudios'               => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/comprobante_estudios/',
            'formatos-alta-recomendacion'          => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/cartas_recomendacion/',
            'formatos-alta-curp'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/curp/',
            'formatos-alta-rfc'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/rfc/',
            'formatos-alta-antecedentes'           => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/carta_antecedentes/',
            'formatos-alta-infonavit'              => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/acta_infonavit/',
            'formatos-baja'                        => dirname(__DIR__, 2) . '/public/uploads/archivos/documentos-personal/solicitud-baja/',
            'formato-descarga-merma'               => [ dirname(__DIR__, 2) . '/public/uploads/archivos/formato-descarga-merma/', dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/archivos/tuxpan/', ],
            'formato-descarga-merma-firma'         => [ dirname(__DIR__, 2) . '/public/uploads/archivos/formato-descarga-merma-firmas/', dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/firmas/firma-tuxpan/', dirname(__DIR__, 2) . '/public/uploads/firmas/', dirname(__DIR__, 2) . '/public/imgs/firma-tuxpan/', dirname(__DIR__, 2) . '/imgs/firma-tuxpan/', ],
            'pivoteo'                               => dirname(__DIR__, 2) . '/public/uploads/archivos/Pivoteo/',
            'pivoteo-firma'                         => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'cuenta-litros'                         => dirname(__DIR__, 2) . '/public/uploads/archivos/cuenta-litros/',
            'inventarios-diarios'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'analisis-compra'                       => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'proveedores'                           => dirname(__DIR__, 2) . '/public/uploads/archivos/proveedores/',
            'camioneta-saveiro'                    => [ dirname(__DIR__, 2) . '/public/uploads/archivos/camioneta-saveiro/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'pedido-aditivo'                       => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'recibos-nomina'                       => dirname(__DIR__, 2) . '/public/uploads/archivos/recibos-nomina-v2/acuses/',
            'recibos-nomina-firma'                 => dirname(__DIR__, 2) . '/public/uploads/archivos/recibos-nomina-v2/firmados/',
            'vacaciones'                           => dirname(__DIR__, 2) . '/public/uploads/archivos/vacaciones/',
            'recibos-aguinaldo'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/recibos-nomina-v2/aguinaldo/',
            'recibos-mexdesa'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/recibos-nomina-v2/recibos-mexdesa/',
            'recibos-nomina-acuse'                 => dirname(__DIR__, 2) . '/public/uploads/archivos/recibo-nomina-acuse/',
            'calibracion-dispensarios'              => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'mantenimiento-preventivo'               => dirname(__DIR__, 2) . '/public/uploads/archivos/mantenimiento/',
            'orden-mantenimiento'                   => [ dirname(__DIR__, 2) . '/public/uploads/archivos/', dirname(__DIR__, 2) . '/public/uploads/archivos/material-evidencia/', dirname(__DIR__, 2) . '/public/uploads/firmas/orden-mantenimiento/', dirname(__DIR__, 2) . '/public/uploads/firmas/firma-tuxpan/', dirname(__DIR__, 2) . '/public/uploads/firmas/', dirname(__DIR__, 2) . '/public/imgs/firma/', dirname(__DIR__, 2) . '/imgs/firma/', ],
            'orden-mantenimiento-causa'             => [ dirname(__DIR__, 2) . '/public/uploads/archivos/orden-mantenimiento-causa/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'prueba-eficiencia'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/prueba-eficiencia/',
            'maquinaria-equipo'                      => dirname(__DIR__, 2) . '/public/uploads/archivos/maquinaria-equipo/',
            'maquinaria-mantenimiento'               => dirname(__DIR__, 2) . '/public/uploads/archivos/maquinaria-mantenimiento/',
            'maquinaria-mantenimiento-evidencias'    => dirname(__DIR__, 2) . '/public/uploads/archivos/maquinaria-mantenimiento/',
            'maquinaria-mantenimiento-firmas'        => dirname(__DIR__, 2) . '/public/uploads/firmas/maquinaria-mantenimiento/',
            'tpv'                                    => dirname(__DIR__, 2) . '/public/uploads/archivos/',
            'tpv-anexos'                             => dirname(__DIR__, 2) . '/public/uploads/archivos/tpv/anexos/',
            'cre'                                    => dirname(__DIR__, 2) . '/public/uploads/archivos/cre/',
            'sala-juntas'                           => dirname(__DIR__, 2) . '/public/uploads/archivos/sala-juntas/',
            'constancia-fiscal'                     => dirname(__DIR__, 2) . '/public/uploads/archivos/constancia-situacion-es/',
            'incidencias'                           => dirname(__DIR__, 2) . '/public/uploads/archivos/incidencias/',
            'comunicados'                           => dirname(__DIR__, 2) . '/public/uploads/archivos/comunicados/',
            'miselanea'                             => [ dirname(__DIR__, 2) . '/public/uploads/archivos/miselanea/', dirname(__DIR__, 2) . '/public/uploads/archivos/', ],
            'soporte-actividades'                   => dirname(__DIR__, 2) . '/public/uploads/archivos/soporte/actividades/',
            'soporte-evidencias'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/soporte/evidencias/',
            'procedimientos-modulos'                => dirname(__DIR__, 2) . '/public/uploads/archivos/procedimientos-modulos/',
            'sasisopa-analisis-riesgo'               => dirname(__DIR__, 2) . '/public/uploads/archivos/analisis-riesgo/',
            'sasisopa-seguridad-contratistas'        => dirname(__DIR__, 2) . '/public/uploads/archivos/seguridad-contratistas/',
            'sasisopa-simulacros'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/protocolo/',
            'sasisopa-informe-revision-resultados'   => dirname(__DIR__, 2) . '/public/uploads/archivos/informe-revision-resultados/',
            'sasisopa-atencion-hallazgos'            => dirname(__DIR__, 2) . '/public/uploads/archivos/atencion-hallazgos/',
            'sasisopa-auditorias'                    => dirname(__DIR__, 2) . '/public/uploads/archivos/auditorias/',
            'sasisopa-incidentes-accidentes'         => dirname(__DIR__, 2) . '/public/uploads/archivos/incidentes-accidentes/',
            'sasisopa-revision-resultados'           => dirname(__DIR__, 2) . '/public/uploads/archivos/revision-resultados/',
            'sasisopa-evaluacion-desempeno'          => dirname(__DIR__, 2) . '/public/uploads/archivos/evaluacion-desempeño/',
            'sasisopa-control-documentos-registros' => dirname(__DIR__, 2) . '/public/uploads/archivos/Fo.ADMONGAS/',
            'sasisopa-control-documentos-procedimientos' => dirname(__DIR__, 2) . '/public/uploads/archivos/procedimientos/',
            'sasisopa-integridad-mecanica-manual'    => dirname(__DIR__, 2) . '/public/uploads/archivos/manuales/',
            'sasisopa-comunicacion-evidencias'       => dirname(__DIR__, 2) . '/public/uploads/archivos/evidencias/',
            'sasisopa-lista-asistencia-evidencias'   => dirname(__DIR__, 2) . '/public/uploads/archivos/evidencias/',
            'sasisopa-reporte-diario-facturas'       => dirname(__DIR__, 2) . '/public/uploads/archivos/cre/',

        ];

        // 1. Si es un documento confidencial del personal, se valida con el servicio sensible
        if (str_starts_with($tipo, 'docs-personal-')) {
            // El resolvedor sensible autoriza la descarga y busca en las rutas importadas.
            $resolved = SensitiveDownloadAuthorizationService::resolve($tipo, $file);
            if ($resolved === null) {
                header("Location: /404");
                exit;
            }
            $baseDirectorio = $resolved['base'];
            $rutaCompleta   = $resolved['path'];
        } else {
            // 2. Para todos los demás tipos declarados en el array $rutas (incluido camioneta-saveiro)
            if (!isset($rutas[$tipo])) {
                http_response_code(403);
                echo 'Tipo no permitido';
                exit;
            }

            $directorios = (array) $rutas[$tipo];
            $baseDirectorio = null;
            $rutaCompleta = null;
            foreach ($directorios as $directorio) {
                $candidato = rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . $file;
                if (is_file($candidato)) {
                    $baseDirectorio = $directorio;
                    $rutaCompleta = $candidato;
                    break;
                }
            }
            if ($baseDirectorio === null || $rutaCompleta === null) {
                header("Location: /404");
                exit;
            }
        }

        // ============================================================
        // SECURITY: Validación de path traversal y existencia
        // ============================================================
        $realPath = realpath($rutaCompleta);
        $basePath = realpath($baseDirectorio);

        $basePathPrefix = $basePath ? rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR : '';
        if (!$realPath || !$basePath || !is_file($realPath) || !is_readable($realPath)
            || $basePathPrefix === '' || !str_starts_with($realPath, $basePathPrefix)) {
            header("Location: /404");
            exit;
        }

        // Los archivos Saveiro de la versión anterior se guardaban sin extensión.
        // Inferirla desde el contenido físico para que el archivo descargado abra correctamente.
        $mimeDetectado = null;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($tipo === 'camioneta-saveiro' && $extension === '') {
            $mimeDetectado = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath) ?: null;
            if ($mimeDetectado === 'application/octet-stream') {
                $handle = @fopen($realPath, 'rb');
                $firma = $handle !== false ? fread($handle, 5) : '';
                if ($handle !== false) fclose($handle);
                if ($firma === '%PDF-') $mimeDetectado = 'application/pdf';
            }
            $extensionInferida = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/zip' => 'zip',
            ][$mimeDetectado] ?? '';
            if ($extensionInferida !== '') $file .= '.' . $extensionInferida;
        }

        // Sanitizar nombre de archivo para Content-Disposition
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file));

        // ?view=1 para visualización inline (PDF/imágenes en iframe)
        $viewMode = isset($_GET['view']);

        if ($viewMode) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $contentTypes = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
            ];
            $contentType = $contentTypes[$ext] ?? ($tipo === 'camioneta-saveiro' ? $mimeDetectado : null);
            if ($contentType !== null) {
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: inline; filename="' . $safeFilename . '"');
            } else {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            }
        } else {
            // FORZAR DESCARGA
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($tipo === 'camioneta-saveiro' && $mimeDetectado !== null ? $mimeDetectado : 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            header('Pragma: public');
        }

        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }
}
