<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Estacion;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentosRegistrosController extends BaseController{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(){
        $title = '8. CONTROL DE DOCUMENTOS Y REGISTROS';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $idEstacion = $this->estacionModulo();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('controldocumentosregistros/index', $data,'sasisopa');
    }

    public function requisitosLegales()
    {
        $title = 'Control y documentos de Requisitos Legales';

        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            '8. CONTROL DE DOCUMENTOS Y REGISTROS',
            '/sasisopa/control-documentos-registros'
        );
        Breadcrumb::add($title, '');

        $idEstacion = $this->estacionModulo();

        $niveles = [
            'municipal' => 'Municipal',
            'federal'   => 'Federal',
            'estatal'   => 'Estatal',
            'varios'    => 'Varios',
        ];

        $requisitos = [];

        foreach ($niveles as $key => $titulo) {

            $requisitos[$key] = RequisitosLegalesCalendario::with([
                'requisito',
                'matrizReciente'
            ])
            ->where('id_estacion', $idEstacion)
            ->where('nivel_gobierno', $key)
            ->where('estado', 1)
            ->get();
        }

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
            'niveles' => $niveles,
            'requisitos' => $requisitos,
            'links' => [
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render(
            'controldocumentosregistros/requisitos-legales',
            $data,
            'sasisopa'
        );
    }

    public function pdfRequisitosLegales(){

    $idEstacion = $this->estacionModulo();

    $registro = Estacion::find($idEstacion);

    if (!$registro) {
        return "No se encontró la información";
    }

    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $niveles = [
        'municipal' => 'Municipal',
        'federal'   => 'Federal',
        'estatal'   => 'Estatal',
        'varios'    => 'Varios',
    ];

    $requisitos = [];

    foreach ($niveles as $key => $titulo) {

        $requisitos[$key] = RequisitosLegalesCalendario::with([
            'requisito',
            'matrizReciente'
        ])
        ->where('id_estacion', $idEstacion)
        ->where('nivel_gobierno', $key)
        ->where('estado', 1)
        ->get();
    }

    $html = '
    <!DOCTYPE html>
    <html>

    <head>

        <meta charset="UTF-8">

        <title>
            Control y documentos de Requisitos Legales
        </title>

        <link rel="stylesheet"
            href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">

        <style>

            .header{
                background:#0d6efd;
                color:#FFF;
                padding:10px;
                font-weight:bold;
                margin-top:15px;
            }

        </style>

    </head>

    <body class="fs-6">

        <div class="text-center">
            <img src="' . $logo . '" style="width:250px;">
        </div>

        <div class="text-center mt-3">
            ' . $registro->permisocre . '
        </div>

        <div class="text-center mt-1">
            ' . $registro->razonsocial . '
        </div>

        <div class="text-center mt-1">
            ' . $registro->direccioncompleta . '
        </div>

        <h1 class="text-center mt-4">
            Control y documentos de Requisitos Legales
        </h1>
    ';

    foreach ($niveles as $key => $titulo) {

        $html .= '

        <div class="header">
            Nivel de gobierno <b>' . $titulo . '</b>
        </div>

        <table class="table">

            <thead>

                <tr>

                    <th width="20%">
                        Dependencia
                    </th>

                    <th width="20%">
                        Permiso
                    </th>

                    <th width="60%">
                        Fundamento
                    </th>   
                </tr>

            </thead>

            <tbody>
        ';

        if ($requisitos[$key]->count() > 0) {

            foreach ($requisitos[$key] as $item) {
            
                $html .= '

                <tr>
                    <td>
                        <b>
                            ' . ($item->requisito->dependencia ?? 'S/I') . '
                        </b>
                    </td>
                    <td>
                        <b>
                            ' . ($item->requisito->permiso ?? $item->requisito_legal) . '
                        </b>
                    </td>
                    <td>
                        ' . ($item->requisito->fundamento ?? 'S/I') . '
                    </td>
                </tr>
                ';
            }

        } else {

            $html .= '
            <tr>
                <td colspan="6" class="text-center">
                    No hay registros disponibles
                </td>
            </tr>
            ';
        }

        $html .= '
            </tbody>
        </table>
        ';
    }

    $html .= '
    </body>
    </html>
    ';

    // ======================
    // PDF
    // ======================

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return $dompdf->stream(
        'Control-documentos-requisitos-legales.pdf',
        ['Attachment' => true]
    );

    }
    public function sistemaAdministracion(){

    $title = 'Control y documentos del Sistema de Administración';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('8. CONTROL DE DOCUMENTOS Y REGISTROS', '/sasisopa/control-documentos-registros');
        Breadcrumb::add($title, '');

        $idEstacion = $this->estacionModulo();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('controldocumentosregistros/sistema-administracion', $data,'sasisopa');

    }

    public function pdfSistemaAdministracion(){

    $titulo = 'Control y documentos del Sistema de Administración';

$registros = [
    [
        'elemento' => '1 POLÍTICA',
        'codigo'   => 'Fo.ADMONGAS.001',
        'documento'=> 'Formato de Revisión de la política del SA',
        'rowspan'  => 1
    ],

    [
        'elemento' => '2 IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES',
        'codigo'   => 'DLES/SA/005',
        'documento'=> 'Análisis de Riesgo del Sector Hidrocarburos',
        'rowspan'  => 3
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.002',
        'documento'=> 'Identificación y evaluación de Aspectos e Impactos Ambientales.'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.003',
        'documento'=> 'Identificación y evaluación de Riesgos y Peligros'
    ],

    [
        'elemento' => '3 REQUISITOS LEGALES',
        'codigo'   => 'Fo.ADMONGAS.004',
        'documento'=> 'Calendario Anual de renovación de Requisitos legales',
        'rowspan'  => 1
    ],

    [
        'elemento' => '4 OBJETIVOS, METAS E INDICADORES',
        'codigo'   => 'Fo.ADMONGAS.005',
        'documento'=> 'Reporte Estadístico Diario',
        'rowspan'  => 3
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.006',
        'documento'=> 'Seguimiento de objetivos y metas'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.007',
        'documento'=> 'Seguimiento y reporte de indicadores'
    ],

    [
        'elemento' => '6 COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO',
        'codigo'   => 'Fo.ADMONGAS.008',
        'documento'=> 'Fichas de personal',
        'rowspan'  => 2
    ],
    [
        'codigo'   => 'FO.ADMONGAS.009',
        'documento'=> 'Registros de la implementación del programa de Capacitación.'
    ],

    [
        'elemento' => '7 COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA',
        'codigo'   => 'Fo.ADMONGAS.010',
        'documento'=> 'Bitácoras con el registro de la atención y el seguimiento a la comunicación interna y externa.',
        'rowspan'  => 1
    ],

    [
        'elemento' => '10 CONTROL DE ACTIVIDADES Y PROCESOS',
        'codigo'   => 'DLES.ADMONGAS.001',
        'documento'=> 'Procedimientos de operación, seguridad y mantenimiento',
        'rowspan'  => 4
    ],
    [
        'codigo'   => 'DLES.ADMONGAS.002',
        'documento'=> 'Bitácora de mantenimiento preventivo y correctivo'
    ],
    [
        'codigo'   => 'DLES.ADMONGAS.003',
        'documento'=> 'Bitácora de operación'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.011',
        'documento'=> 'Programa anual de mantenimiento'
    ],

    [
        'elemento' => '11 INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD',
        'codigo'   => 'DLES.ADMONGAS.001',
        'documento'=> 'Procedimientos de operación, seguridad y mantenimiento',
        'rowspan'  => 3
    ],
    [
        'codigo'   => 'DLES.ADMONGAS.002',
        'documento'=> 'Bitácora de mantenimiento preventivo y correctivo'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.011',
        'documento'=> 'Programa anual de mantenimiento'
    ],

    [
        'elemento' => '12 SEGURIDAD DE CONTRATISTAS',
        'codigo'   => 'DLES.ADMONGAS.001',
        'documento'=> 'Procedimientos de operación, seguridad y mantenimiento',
        'rowspan'  => 5
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.012',
        'documento'=> 'Autorización para realizar trabajos peligrosos'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.013',
        'documento'=> 'Formato para requisición de obra o servicio.'
    ],
    [
        'codigo'   => 'FO.ADMONGAS.014',
        'documento'=> 'Formato para entrega de información al contratista.'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.015',
        'documento'=> 'Listas de verificación.'
    ],

    [
        'elemento' => '13 PREPARACIÓN Y RESPUESTA A EMERGENCIAS',
        'codigo'   => 'DLES/SA/004',
        'documento'=> 'Protocolo de Respuesta Emergencias',
        'rowspan'  => 3
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.016',
        'documento'=> 'Programa anual de simulacros'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.16A',
        'documento'=> 'Evaluación de simulacros'
    ],

    [
        'elemento' => '14 MONITOREO, VERIFICACIÓN Y EVALUACIÓN',
        'codigo'   => 'Fo.ADMONGAS.017',
        'documento'=> 'Programa de implementación del Sistema de administración',
        'rowspan'  => 7
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.019',
        'documento'=> 'Relación de equipos sometidos a calibración'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.020',
        'documento'=> 'Calendario de calibraciones'
    ],
    [
        'codigo'   => 'DLES.ADMONGAS.002',
        'documento'=> 'Bitácora con los resultados de la calibración y mantenimiento de equipos'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.021',
        'documento'=> 'Matriz de evaluación del cumplimiento legal.'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.022',
        'documento'=> 'Informe de Resultados de la evaluación del cumplimiento de requisitos legales y otros aplicables al Proyecto.'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.018',
        'documento'=> 'Programa de Atención de Hallazgos (acciones preventivas y correctivas)'
    ],

    [
        'elemento' => '15 AUDITORÍAS',
        'codigo'   => 'Fo.ADMONGAS.023',
        'documento'=> 'Programa Anual de Auditorías (Auditorías internas y, en su caso, la Auditoría Externa).',
        'rowspan'  => 3
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.024',
        'documento'=> 'El informe de Auditoría'
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.025',
        'documento'=> 'Plan de atención de hallazgos'
    ],

    [
        'elemento' => '16 INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES',
        'codigo'   => 'Fo.ADMONGAS.026',
        'documento'=> 'Informe detallado de la Investigación de Causa Raíz de los Eventos tipo 1',
        'rowspan'  => 1
    ],

    [
        'elemento' => '17 REVISIÓN DE RESULTADOS',
        'codigo'   => 'FO.ADMONGAS.027',
        'documento'=> 'Informe de revisión de resultados emitido por la alta dirección.',
        'rowspan'  => 1
    ],

    [
        'elemento' => '18 INFORMES DE DESEMPEÑO',
        'codigo'   => 'Fo.ADMONGAS.028',
        'documento'=> 'IED. Mientras la agencia no emita un formato para este apartado se utilizará provisionalmente.',
        'rowspan'  => 2
    ],
    [
        'codigo'   => 'Fo.ADMONGAS.029',
        'documento'=> 'Bitácoras de las visitas de control de la implementación de los procedimientos técnicos y administrativos especificados en las DACG SASISOPA Expendio al Público.'
    ]
];

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>'.$titulo.'</title>
 <link rel="stylesheet"
            href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
</head>

<body>

<div class="text-center" style="margin-top: 40px;"><h1>'.$titulo.'</h1></div>
';

$html .= '<table class="table table-sm table-bordered" style="font-size: .95rem;margin-top: 40px;">';
$html .= '<tr>';
$html .= '<td class="align-middle bg-light" style="padding: 15px;"><b>Elemento del Sistema de Administración</b></td>';
$html .= '<td class="align-middle bg-light"><b>Código de control</b></th>';
$html .= '<td class="align-middle bg-light"><b>Nombre del documento o registro</b></td>';
$html .= '</tr>';
$html .= '<tbody>';

$html .= '<tr>
<td class="align-middle">1 POLÍTICA</td>
<td class="align-middle">Fo.ADMONGAS.001</td>
<td class="align-middle">Formato de Revisión de la política del SA</td>
</tr>';
$html .= '<tr>
<td class="align-middle" rowspan="3">2 IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES</td>
<td class="align-middle">DLES/SA/005</td>
<td class="align-middle">Análisis de Riesgo del Sector Hidrocarburos</td>
</tr>';
$html .= '
<tr>
<td class="align-middle">Fo.ADMONGAS.002</td>
<td class="align-middle">Identificación y evaluación de Aspectos e Impactos Ambientales.</td>
</tr>
<tr>
<td class="align-middle">Fo.ADMONGAS.003</td>
<td class="align-middle">Identificación y evaluación de Riesgos y Peligros</td>
</tr>
<tr>
<td class="align-middle">3 REQUISITOS LEGALES</td>
<td class="align-middle">Fo.ADMONGAS.004</td>
<td class="align-middle">Calendario Anual de renovación de Requisitos legales</td>
</tr>';
$html .= '<tr>
<td class="align-middle" rowspan="3">4 OBJETIVOS, METAS E INDICADORES</td>
<td class="align-middle">Fo.ADMONGAS.005</td>
<td class="align-middle">Reporte Estadístico Diario</td>
</tr>
<tr>
<td class="align-middle">Fo.ADMONGAS.006</td>
<td class="align-middle">Seguimiento de objetivos y metas</td>
</tr>
<tr>
<td class="align-middle">Fo.ADMONGAS.007</td>
<td class="align-middle">Seguimiento y reporte de indicadores</td>
</tr>
<tr>
<td class="align-middle" rowspan="2">6 COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO</td>
<td class="align-middle">Fo.ADMONGAS.008</td>
<td class="align-middle">Fichas de personal</td>
</tr>
<tr>
<td class="align-middle">FO.ADMONGAS.009</td>
<td class="align-middle">Registros de la implementación del programa de Capacitación. </td>
</tr>
<tr>
<td class="align-middle">7 COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA</td>
<td class="align-middle">Fo.ADMONGAS.010</td>
<td class="align-middle">Bitácoras con el registro de la atención y el seguimiento a la comunicación interna y externa.</td>
</tr>
<tr>
<td class="align-middle" rowspan="4">10 CONTROL DE ACTIVIDADES Y PROCESOS</td>
            <td class="align-middle">DLES.ADMONGAS.001</td>
            <td class="align-middle">Procedimientos de operación, seguridad y mantenimiento</td>
          </tr>
          <tr>
            <td class="align-middle">DLES.ADMONGAS.002</td>
            <td class="align-middle">Bitácora de mantenimiento preventivo y correctivo</td>
            </tr>          
          <tr>
            <td class="align-middle">DLES.ADMONGAS.003 </td>
            <td class="align-middle">Bitácora de operación </td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.011</td>
            <td class="align-middle">Programa anual de mantenimiento</td>
            </tr>
          <tr>
            <td class="align-middle" rowspan="3">11 INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD</td>
            <td class="align-middle">DLES.ADMONGAS.001</td>
            <td class="align-middle">Procedimientos de operación, seguridad y mantenimiento</td>
            </tr>
          <tr>
            <td class="align-middle">DLES.ADMONGAS.002</td>
            <td class="align-middle">Bitácora de mantenimiento preventivo y correctivo</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.011</td>
            <td class="align-middle">Programa anual de mantenimiento</td>
            </tr>
          <tr>
            <td class="align-middle" rowspan="5">12 SEGURIDAD DE CONTRATISTAS</td>
            <td class="align-middle">DLES.ADMONGAS.001</td>
            <td class="align-middle">Procedimientos de operación, seguridad y mantenimiento </td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.012</td>
            <td class="align-middle">Autorización para realizar trabajos peligrosos</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.013</td>
            <td class="align-middle">Formato para requisición de obra o servicio.</td>
            </tr>
          <tr>
            <td class="align-middle">FO.ADMONGAS.014</td>
            <td class="align-middle">Formato para entrega de información al contratista.</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.015</td>
            <td class="align-middle">Listas de verificación.</td>
            </tr>
          <tr>
            <td class="align-middle" rowspan="3">13 PREPARACIÓN Y RESPUESTA A EMERGENCIAS</td>
            <td class="align-middle">DLES/SA/004</td>
            <td class="align-middle">Protocolo de Respuesta Emergencias</td>
            </tr>
          
          <tr>
            <td class="align-middle">Fo.ADMONGAS.016</td>
            <td class="align-middle">Programa anual de simulacros</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.16ª</td>
            <td class="align-middle">Evaluación de simulacros</td>
            </tr>
          <tr>
            <td class="align-middle" rowspan="7">14 MONITOREO, VERIFICACIÓN Y EVALUACIÓN</td>
            <td class="align-middle">Fo.ADMONGAS.017</td>
            <td class="align-middle">Programa de implementación del Sistema de administración </td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.019</td>
            <td class="align-middle">Relación de equipos sometidos a calibración </td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.020</td>
            <td class="align-middle">Calendario de calibraciones</td>
            </tr>
          <tr>
            <td class="align-middle">DLES.ADMONGAS.002</td>
            <td class="align-middle">Bitácora con los resultados de la calibración y mantenimiento de equipos</td>
          </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.021</td>
            <td class="align-middle">Matriz de evaluación del cumplimiento legal.</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.022</td>
            <td class="align-middle">Informe de Resultados de la evaluación del cumplimiento de requisitos legales y otros aplicables al Proyecto.</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.018 </td>
            <td class="align-middle">Programa de Atención de Hallazgos (acciones preventivas y correctivas)</td>
            </tr>
           <tr>
            <td class="align-middle" rowspan="3">15 AUDITORÍAS</td>
            <td class="align-middle">Fo.ADMONGAS.023</td>
            <td class="align-middle">Programa Anual de Auditorías (Auditorías internas y, en su caso, la Auditoría Externa).</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.024</td>
            <td class="align-middle">El informe de Auditoría</td>
            </tr>
          <tr>
            <td class="align-middle">Fo.ADMONGAS.025</td>
            <td class="align-middle">Plan de atención de hallazgos </td>
            </tr>
          <tr>
            <td class="align-middle">16 INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES</td>
            <td class="align-middle">Fo.ADMONGAS.026</td>
            <td class="align-middle">Informe detallado de la Investigación de Causa Raíz de los Eventos tipo 1 </td>
            </tr>
          <tr>
            <td class="align-middle">17 REVISIÓN DE RESULTADOS</td>
            <td class="align-middle">FO.ADMONGAS.027</td>
            <td class="align-middle">Informe de revisión de resultados emitido por la alta dirección, bajo el FO.ADMONGAS.027</td>
            </tr>
           <tr>
            <td class="align-middle" rowspan="2">18 INFORMES DE DESEMPEÑO</td>
            <td class="align-middle">Fo.ADMONGAS.028</td>
            <td class="align-middle">IED. Mientras la agencia no emita un formato para este apartado se utilizara provisionalmente</td>
            </tr>
           <tr>
            <td class="align-middle">Fo.ADMONGAS.029 </td>
            <td class="align-middle">Bitácoras de las visitas de control de la implementación de los procedimientos técnicos y administrativos especificados en las DACG SASISOPA Expendio al Público.</td>
            </tr>';

$html .= '</tbody>';
$html .= '</table>';

$html .= '
</body>
</html>
';

       $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return $dompdf->stream(
        'Control_y_documentos_del_Sistema_de_Administracion.pdf',
        ['Attachment' => true]
    );

    }
}