<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\MantenimientoCorrectivo;
use App\Models\Sasisopa\MantenimientoCorrectivoEvidencia;
use Dompdf\Dompdf;
use Dompdf\Options;

class MantenimientoCorrectivoController extends BaseController
{
    protected string $modulo = 'sasisopa';
    public function index()
    {

    $title = 'Mantenimiento Correctivo';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');

        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );

        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion(
            $this->modulo
        );

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/mantenimientocorrectivo.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/mantenimientocorrectivo.action.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/mantenimiento-correctivo',$data,'sasisopa');
       
    }

    public function datatable(){

    $year = sanitize_input($_GET['year'] ?? null,'int');
    $mes = sanitize_input($_GET['mes'] ?? null,'int');

      $data = MantenimientoCorrectivo::where('id_estacion',$this->estacionId())
        ->when($year, function ($q) use ($year) {
                $q->whereYear('fechacreacion',$year);
            })
            ->when($mes, function ($q) use ($mes) {
                $q->whereMonth('fechacreacion',$mes);
            })
        ->orderByDesc('id')
        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'folio' => $item->folio,
                'id_estacion' => $item->id_estacion,
                'id_usuario' => $item->id_usuario,
                'fechacreacion' => formatDate($item->fechacreacion),
                'fechacreacion_larga' => formatearFecha($item->fechacreacion),
                'horacreacion' => $item->horacreacion->format('h:i a'),
                'nombre_equipo' => $item->nombre_equipo,
                'descripcion_hallazgo' => $item->descripcion_hallazgo,
                'descripcion_actividad' => $item->descripcion_actividad,
                'herramienta' => $item->herramienta,
            ];
        });

    echo json_encode([
        'data' => $data,
        'permisos' => [
            'editar' => ModuloService::validaPermiso($this->modulo,'editar')
        ]
    ]);

    }

    public function update()
    {
        header('Content-Type: application/json');

        try{

            $data = json_decode(file_get_contents('php://input'),true);

            $registro = MantenimientoCorrectivo::where(
                'id_estacion',
                $this->estacionId()
            )->find($data['id']);

            if(!$registro){
                echo json_encode([
                    'success' => false,
                    'message' => 'El registro no existe'
                ]);
                return;
            }

            $registro->update([
                'nombre_equipo' => sanitize_input(
                    $data['nombre_equipo'] ?? null,
                    'string'
                ),
                'descripcion_hallazgo' => sanitize_input(
                    $data['descripcion_hallazgo'] ?? null,
                    'string'
                ),
                'descripcion_actividad' => sanitize_input(
                    $data['descripcion_actividad'] ?? null,
                    'string'
                ),
                'herramienta' => sanitize_input(
                    $data['herramienta'] ?? null,
                    'string'
                ),
            ]);

            echo json_encode([
                'success' => true,
                'message' =>
                'Mantenimiento Correctivo actualizado correctamente'
            ]);

        }catch(\Throwable $e){

            echo json_encode([
                'success' => false,
                'message' =>
                'Error al actualizar Mantenimiento Correctivo'
            ]);
        }
    }

    public function evidencias(int $id)
    {
        $data = MantenimientoCorrectivoEvidencia::where(
            'id_mantenimiento',
            $id
        )->get();

        echo json_encode([
            'data' => $data
        ]);
    }

    public function createEvidencia()
    {
    header('Content-Type: application/json');

    try{

        $id_mantenimiento = sanitize_input(
            $_POST['id_mantenimiento'] ?? null,
            'int'
        );

        if(!$id_mantenimiento){

            echo json_encode([
                'success' => false,
                'message' => 'Mantenimiento inválido'
            ]);

            return;
        }

        if(!isset($_FILES['imagenes'])){

            echo json_encode([
                'success' => false,
                'message' => 'No se recibieron imágenes'
            ]);

            return;
        }

        $files = $_FILES['imagenes'];

        $directory = 'uploads/archivos/mantenimiento';

        // CREAR DIRECTORIO
        if(!is_dir($directory)){

            mkdir(
                $directory,
                0777,
                true
            );
        }

        $guardadas = 0;

        foreach($files['tmp_name'] as $key => $tmp_name){

            if(empty($tmp_name)){
                continue;
            }

            $name = $files['name'][$key];

            $extension = strtolower(
                pathinfo(
                    $name,
                    PATHINFO_EXTENSION
                )
            );

            // VALIDAR EXTENSION
            if(
                !in_array(
                    $extension,
                    ['jpg','jpeg','png','webp']
                )
            ){
                continue;
            }

            // VALIDAR MIME
            $mime = mime_content_type($tmp_name);

            if(
                !str_starts_with(
                    $mime,
                    'image/'
                )
            ){
                continue;
            }

            $maxWidth = 1600;
            $quality = 80;

            [$width, $height] = getimagesize($tmp_name);

            if($width > $maxWidth){

                $newWidth = $maxWidth;

                $newHeight = intval(
                    ($height * $newWidth) / $width
                );

            }else{

                $newWidth = $width;
                $newHeight = $height;
            }

            // CREAR IMAGEN ORIGINAL
            switch($extension){

                case 'jpg':
                case 'jpeg':

                    $source =
                    imagecreatefromjpeg($tmp_name);

                    break;

                case 'png':

                    $source =
                    imagecreatefrompng($tmp_name);

                    break;

                case 'webp':

                    $source =
                    imagecreatefromwebp($tmp_name);

                    break;

                default:
                    continue 2;
            }

            if(!$source){
                continue;
            }

            // NUEVO LIENZO
            $optimized = imagecreatetruecolor(
                $newWidth,
                $newHeight
            );

            // CONSERVAR TRANSPARENCIA PNG/WEBP
            if(
                in_array(
                    $extension,
                    ['png','webp']
                )
            ){

                imagealphablending(
                    $optimized,
                    false
                );

                imagesavealpha(
                    $optimized,
                    true
                );
            }

            // REDIMENSIONAR
            imagecopyresampled(

                $optimized,
                $source,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );


            $nuevoNombre =
                'MANTENIMIENTOC-'
                .$id_mantenimiento
                .'-'
                .uniqid()
                .'.webp';

            $ruta =
                $directory
                .DIRECTORY_SEPARATOR
                .$nuevoNombre;

            $saved = imagewebp(
                $optimized,
                $ruta,
                $quality
            );

            imagedestroy($source);
            imagedestroy($optimized);
            if($saved){

                $url =
                    $_ENV['APP_URL']
                    .'/uploads/archivos/mantenimiento/'
                    .$nuevoNombre;

                MantenimientoCorrectivoEvidencia::create([

                    'id_mantenimiento' => $id_mantenimiento,

                    'url' => $url,

                    'nombre' => $nuevoNombre
                ]);

                $guardadas++;
            }
        }

        if($guardadas <= 0){

            echo json_encode([
                'success' => false,
                'message' => 'No se pudieron guardar imágenes'
            ]);

            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Evidencias agregadas correctamente'
        ]);

    }catch(\Throwable $e){

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    }

    public function deleteEvidencia()
    {
        header('Content-Type: application/json');

        try{

            $data = json_decode(file_get_contents('php://input'),true);

            $registro = MantenimientoCorrectivoEvidencia::find($data['id']);

            if(!$registro){
                echo json_encode([
                    'success' => false,
                    'message' => 'La evidencia no existe'
                ]);

                return;
            }

            $path ='uploads/archivos/mantenimiento/'
            .$registro->nombre;

            if(file_exists($path)){
                unlink($path);
            }

            $registro->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Evidencia eliminada correctamente'
            ]);

        }catch(\Throwable $e){

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar evidencia'
            ]);
        }
    }

    public function pdf()
    {
        [
            'year' => $year,
            'mes' => $mes
        ] = $this->filtros();

        $estacion = Estacion::find(
            $this->estacionId()
        );

        if (!$estacion) {
            return 'No se encontró información';
        }

        $registros = $this->queryRegistros(
            $year,
            $mes
        )->get();

        $logo = $_ENV['APP_URL']. '/assets/images/logos/Logo.png';

        $tituloFecha = !empty($mes)
            ? nombremes($mes) . ' ' . $year
            : $year;

        $html = '
        <!DOCTYPE html>
        <html>

        <head>

            <meta charset="UTF-8">
            <style>
                @page{
                    margin:0.6cm 0.6cm;
                }
                body{
                    margin:0;
                    font-family:Arial, Helvetica, sans-serif;
                    color:#212529;
                    font-size:0.9em;
                }
                table{
                    width:100%;
                     border-collapse:collapse;
                        table-layout:fixed;
                }
                th,
                td{
                    border:1px solid #dee2e6;
                    padding:5px;
                    vertical-align:top;
                }

                .border-0{
                    border:0 !important;
                }

                .text-center{
                    text-align:center;
                }

                .text-end{
                    text-align:right;
                }

                .mt-1{
                    margin-top:5px;
                }

                .mt-2{
                    margin-top:10px;
                }

                .mt-3{
                    margin-top:15px;
                }

                .mt-4{
                    margin-top:20px;
                }

                .table-active{
                    background:#F5F5F5;
                }

                .page-break{
                    page-break-before:always;
                }

                .firma{
                    width:120px;
                    height:auto;
                }

                .evidencia{
                    width:220px;
                    height:180px;
                    object-fit:cover;
                    display:block;
                }

                .fs-small{
                    font-size:12px;
                }

            </style>

        </head>

        <body>

            <div class="text-center" style="margin-top: 200px;">
                <img src="' . $logo . '" style="width:220px;">
            </div>

            <div class="text-center mt-3">
                <strong>
                    Reporte de Mantenimiento Correctivo
                </strong>
            </div>

            <div class="text-center mt-1">
                <strong>
                    ' . $estacion->permisocre . '
                </strong>
            </div>

            <div class="text-center mt-1">
                ' . $estacion->razonsocial . '
            </div>

            <div class="text-center mt-1">
                ' . $estacion->direccioncompleta . '
            </div>

            <div class="text-center mt-1">
                Código: DLES/SA/002
            </div>

            <div class="text-center mt-2">
                <strong>
                    ' . $tituloFecha . '
                </strong>
            </div>
            <div class="page-break"></div>
        ';

        foreach ($registros as $index => $item) {

            $firmaRealiza = $this->obtenerFirma(
                $item->firmas,
                'FPR'
            );

            $firmaSupervisa = $this->obtenerFirma(
                $item->firmas,
                'FPS'
            );

            $html .= '

            <table class="mt-4">
                <tr class="table-active">
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                </tr>
                <tr>
                    <td class="text-center"><strong>' . str_pad($item->folio,3,'0',STR_PAD_LEFT) . '</strong></td>
                    <td class="text-center">' . formatearFecha($item->fechacreacion) . '</td>
                    <td class="text-center">' . optional($item->horacreacion)->format('g:i a') . '</td>
                </tr>
            </table>

            <table class="mt-3">
                <tr class="table-active">
                    <th width="50%">Nombre del equipo o área donde se detecta la no conformidad</th>
                    <th width="50%">Descripción breve del hallazgo detectado que requiere mantenimiento</th>
                </tr>
                <tr>
                    <td>' . nl2br(e($item->nombre_equipo)) . '</td>
                    <td>' . nl2br(e($item->descripcion_hallazgo)) . '</td>
                </tr>
                <tr class="table-active">
                    <th>Descripción de las actividades de mantenimiento</th>
                    <th>Herramienta utilizada para el mantenimiento</th>
                </tr>
                <tr>
                    <td>' . nl2br(e($item->descripcion_actividad)) . '</td>
                    <td>' . nl2br(e($item->herramienta)) . '</td>
                </tr>
            </table>

            <table class="mt-3">
                <tr class="table-active">
                    <th width="50%" class="text-center">Persona que realizó</th>
                    <th width="50%" class="text-center">Persona que supervisó</th>
                </tr>
                <tr>
                    <td class="text-center">
                        ' . (!empty($firmaRealiza?->usuario?->firma)
                            ? '<img
                                class="firma"
                                src="' . $this->firmaUrl(
                                    $firmaRealiza->usuario->firma
                                ) . '">'

                            : ''
                        ) . '
                        <div class="fs-small mt-1">
                            ' . (
                                $firmaRealiza?->usuario?->nombre
                                ?? ''
                            ) . '
                        </div>
                    </td>
                    <td class="text-center">

                        ' . (!empty($firmaSupervisa?->usuario?->firma)
                            ? '<img class="firma"
                                src="' . $this->firmaUrl($firmaSupervisa->usuario->firma) . '">'
                            : ''
                        ) . '
                        <div class="fs-small mt-1">' . ($firmaSupervisa?->usuario?->nombre ?? '') . '</div>
                    </td>
                </tr>
            </table>
            ';

            // =====================================================
            // EVIDENCIAS
            // =====================================================

        if ($item->evidencias->count() > 0) {

            $html .= '

            <div class="mt-4">

                <div
                    style="
                        background:#F5F5F5;
                        padding:8px;
                        font-weight:bold;
                        text-align:center;
                        border:1px solid #dee2e6;
                        margin-bottom:10px;
                    ">

                    Evidencias Fotográficas

                </div>

                <div
                    style="
                        text-align:center;
                        border:1px solid #dee2e6;
                    ">

            ';

            foreach ($item->evidencias as $evidencia) {

                $html .= '

                <div
                    style="
                        display:inline-block;
                        vertical-align:top;
                        margin:5px;
                        padding:5px;
                        background:#fff;
                    ">

                    <img
                        class="evidencia"
                        src="' . $evidencia->url . '">

                </div>
                ';
            }

            $html .= '

                </div>

            </div>
            ';
        }

            if ($index + 1 < $registros->count()) {
                $html .= '<div class="page-break"></div>';
            }
        }

        $html .= '
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled',true);
        $options->set('defaultFont','Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','landscape');
        $dompdf->render();
        return $dompdf->stream('Mantenimiento-Correctivo.pdf',['Attachment' => true]);
    }

    // =====================================================
    // QUERY
    // =====================================================

    private function queryRegistros(?int $year,?int $mes)
    {
        return MantenimientoCorrectivo::query()
            ->with(['evidencias','firmas.usuario:id,nombre,firma'])
            ->where('id_estacion',$this->estacionId())
            ->when(
                $year,
                function ($q) use ($year) {
                    $q->whereYear('fechacreacion',$year);
                }
            )
            ->when(
                $mes,
                function ($q) use ($mes) {
                    $q->whereMonth('fechacreacion',$mes
                    );
                }
            )
            ->orderByDesc('id');
    }

    // =====================================================
    // FILTROS
    // =====================================================

    private function filtros(): array
    {
        return [
            'year' => sanitize_input($_GET['year'] ?? null,'int'),
            'mes' => sanitize_input($_GET['mes'] ?? null,'int')
        ];
    }

    // =====================================================
    // OBTENER FIRMA
    // =====================================================

    private function obtenerFirma($firmas, string $tipo)
    {
        return $firmas
            ->where('tipo_firma',$tipo)
            ->first();
    }

    // =====================================================
    // URL FIRMA
    // =====================================================

    private function firmaUrl(?string $firma): string
    {
        if (empty($firma)) {return '';}
        return $_ENV['APP_URL']. '/uploads/firma-personal/'. $firma;
    }

}