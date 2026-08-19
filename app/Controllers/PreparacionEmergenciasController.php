<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\TelefonosEmergencias;
use App\Models\Sasisopa\ProtocoloEmergencias;
use App\Models\Sasisopa\ProtocoloEmergenciasAnexo;
use App\Models\Sasisopa\ProgramaAnualSimulacros;
use App\Models\Sasisopa\ProgramaAnualSimulacrosEvaluacion;
use App\Models\Sasisopa\ProgramaAnualSimulacrosPersonal;
use App\Models\Sasisopa\ProgramaAnualSimulacrosResumen;
use App\Models\Estacion;
use App\Models\Usuario;
use Dompdf\Dompdf;
use Dompdf\Options;

class PreparacionEmergenciasController extends BaseController{
protected string $modulo = 'sasisopa';
public function index(){

 $title = '13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/preparacionemergencias/index.datatable.init.js?v=' . time(),
                '/js/preparacionemergencias/index.action.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('preparacionemergencias/index', $data,'sasisopa');

}
// Protocolo de respuesta

public function protocoloGet()
{
    header('Content-Type: application/json');

    try {

        $protocolos =
            ProtocoloEmergencias::where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('fechacreacion')
            ->get()
            ->map(function ($item) {

                return [

                    'id' => $item->id,

                    'archivo' => 'uploads/' . $item->archivo,

                    'fecha_formateada' =>
                        formatearFecha($item->fechacreacion->format('Y-m-d')),

                    'fechacreacion' =>
                        $item->fechacreacion->format('Y-m-d')
                ];
            });

        echo json_encode([
            'success' => true,
            'data' => $protocolos
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'data' => $e->getMessage()
        ]);
    }

    exit;
}

public function protocoloCreate()
{
    header('Content-Type: application/json');

    try {

        $fecha =
            sanitize_input(
                $_POST['fecha'] ?? '',
                'string'
            );

        if(empty($fecha)){

            echo json_encode([
                'success' => false,
                'message' => 'La fecha es obligatoria'
            ]);

            return;
        }

        $rutaBd = '';

        if(
            !empty($_FILES['archivo'])
            &&
            $_FILES['archivo']['error']
                === UPLOAD_ERR_OK
        ){

            $carpeta =
                __DIR__
                . '../../../public/uploads/archivos/protocolo/';

            if(!file_exists($carpeta)){

                mkdir_safe(
                    $carpeta,
                    true
                );
            }

            $nombre =
                'PROTOCOLO-'
                .$this->estacionId()
                .'-'
                .time()
                .'.pdf';

            move_uploaded_file(
                $_FILES['archivo']['tmp_name'],
                $carpeta.$nombre
            );

            $rutaBd =
                'archivos/protocolo/'
                .$nombre;
        }

        ProtocoloEmergencias::create([

            'id_estacion' =>
                $this->estacionId(),

            'fechacreacion' =>
                $fecha,

            'archivo' =>
                $rutaBd
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Protocolo agregado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function protocoloUpdate()
{
    header('Content-Type: application/json');

    try {

        $id =
            (int)($_POST['id'] ?? 0);

        $registro =
            ProtocoloEmergencias::find($id);

        if(!$registro){

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        $update = [

            'fechacreacion' =>
                $_POST['fecha']
        ];

        if(
            !empty($_FILES['archivo'])
            &&
            $_FILES['archivo']['error']
                === UPLOAD_ERR_OK
        ){

            $nombre =
                'PROTOCOLO-'.$id.'-'.time().'.pdf';

            $carpeta =
                __DIR__
                . '../../../public/uploads/archivos/protocolo/';

            move_uploaded_file(
                $_FILES['archivo']['tmp_name'],
                $carpeta.$nombre
            );

            $update['archivo'] =
                'archivos/protocolo/'
                .$nombre;
        }

        $registro->update(
            $update
        );

        echo json_encode([
            'success' => true,
            'message' => 'Protocolo actualizado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function protocoloDelete()
{
    header('Content-Type: application/json');

    try {

        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            );

        $id =
            (int)($data['id'] ?? 0);

        $protocolo =
            ProtocoloEmergencias::find($id);

        if(!$protocolo){

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        ProtocoloEmergenciasAnexo::where(
            'id_protocolo',
            $id
        )->delete();

        $protocolo->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Protocolo eliminado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function anexosGet($id)
{
    header('Content-Type: application/json');

    try {

        $data =
            ProtocoloEmergenciasAnexo::where(
                'id_protocolo',
                $id
            )
            ->orderBy('nombre_anexo')
            ->get()
            ->map(function($item){

                return [

                    'id' =>
                        $item->id,

                    'nombre_anexo' =>
                        $item->nombre_anexo,

                    'archivo' =>
                        'uploads/' . $item->archivo,

                    'fecha_formateada' =>
                        formatearFecha(
                            $item->fechacreacion
                                ->format('Y-m-d')
                        )
                ];
            });

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function anexoCreate()
{
    header('Content-Type: application/json');

    try {

        $idProtocolo =
            (int)($_POST['id_protocolo'] ?? 0);

        $nombre =
            sanitize_input(
                $_POST['nombre_anexo'] ?? '',
                'string'
            );

        if(
            !$idProtocolo ||
            empty($nombre)
        ){

            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);

            exit;
        }

        $archivo =
            $_FILES['archivo'];

        $carpeta =
            __DIR__
            . '../../../public/uploads/archivos/protocolo/';

        if(!file_exists($carpeta)){

            mkdir_safe(
                $carpeta,
                true
            );
        }

        $nombreArchivo =
            'ANEXO-'
            . time()
            . '.pdf';

        move_uploaded_file(
            $archivo['tmp_name'],
            $carpeta . $nombreArchivo
        );

        ProtocoloEmergenciasAnexo::create([

            'nombre_anexo' =>
                $nombre,

            'id_protocolo' =>
                $idProtocolo,

            'archivo' =>
                'archivos/protocolo/'
                . $nombreArchivo,

            'fechacreacion' =>
                date('Y-m-d H:i:s')
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Anexo agregado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function anexoDelete()
{
    header('Content-Type: application/json');

    try {

        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            );

        $id =
            (int)($data['id'] ?? 0);

        $anexo =
            ProtocoloEmergenciasAnexo::find(
                $id
            );

        if(!$anexo){

            echo json_encode([
                'success' => false,
                'message' => 'Anexo no encontrado'
            ]);

            exit;
        }

        $anexo->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Anexo eliminado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}


// Telefonos de emergencia
public function telefonosGet()
{
 header('Content-Type: application/json');

    try {

        $telefonos = TelefonosEmergencias::where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderBy('prioridad', 'desc')
            ->get();

        echo json_encode([
            'success' => true,
            'data' => $telefonos
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'data' => []
        ]);
    }

    exit;
}

public function telefonosCreate()
{
     header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    try {

        $titulo = sanitize_input(
            $data['titulo'] ?? '',
            'string'
        );

        $telefono = sanitize_input(
            $data['telefono'] ?? '',
            'string'
        );

        if (
            empty($titulo) ||
            empty($telefono)
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos'
            ]);

            return;
        }

        TelefonosEmergencias::create([

            'id_estacion' => $this->estacionId(),

            'titulo' => $titulo,

            'telefono' => $telefono,

            'prioridad' => 2

        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Teléfono agregado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar'
        ]);
    }

    exit;
}

public function telefonosUpdate()
{
     header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    try {

        $id = (int)($data['id'] ?? 0);

        $registro = TelefonosEmergencias::find($id);

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        $registro->update([

            'titulo' => sanitize_input(
                $data['titulo'],
                'string'
            ),

            'telefono' => sanitize_input(
                $data['telefono'],
                'string'
            )

        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Teléfono actualizado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar'
        ]);
    }

    exit;
}

public function telefonosDelete()
{
    header('Content-Type: application/json');

    try {

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $registro = TelefonosEmergencias::find(
            $data['id'] ?? 0
        );

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Teléfono eliminado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar'
        ]);
    }

    exit;
}

//Programa anual

public function simulacroDatatable(){

    [
        'year' => $year,
        'mes'  => $mes
    ] = $this->filtros();

        $data =
            ProgramaAnualSimulacros::where(
                'id_estacion',
                $this->estacionId()
            )
             ->when(
            $year,
            fn ($q) =>
                $q->whereYear(
                    'fecha',
                    $year
                )
        )

        ->when(
            !empty($mes) && $mes != 13,
            fn ($q) =>
                $q->whereMonth(
                    'fecha',
                    $mes
                )
        )
            ->orderBy('fecha','desc')
            ->get()
            ->map(function ($item) {

                $personal =
                    ProgramaAnualSimulacrosPersonal::where(
                        'id_programa',
                        $item->id
                    )->count();

                $resumen =
                    ProgramaAnualSimulacrosResumen::where(
                        'id_programa',
                        $item->id
                    )->exists();

                $evaluacion =
                    ProgramaAnualSimulacrosEvaluacion::where(
                        'id_programa',
                        $item->id
                    )
                    ->value('archivo');

                return [

                    'id' =>
                        $item->id,

                    'nombre_simulacro' =>
                        $item->nombre_simulacro,

                    'periodicidad' =>
                        $item->periodicidad,

                    'fecha' =>
                        $item->fecha->format('Y-m-d'),

                     'fecha_larga' =>
                        formatearFecha(
                            $item->fecha->format('Y-m-d')
                        ),

                    'personal' => $personal == 0 ? 'No se encontró personal' :  $personal. ' personas',

                    'resumen' =>
                        $resumen,

                    'evaluacion' =>
                        $evaluacion
                            ? '/uploads/'.$evaluacion
                            : ''
                ];
            });

       echo json_encode([
        "data" => $data,
         'permisos' => [
                'eliminar' =>ModuloService::validaPermiso($this->modulo,'eliminar'),
                'editar' =>ModuloService::validaPermiso($this->modulo,'editar')
            ]
    ]);

    exit;
}

private function filtros(): array
{
    return [
        'year'   => sanitize_input($_GET['year'] ?? null, 'int'),
        'mes'    => sanitize_input($_GET['mes'] ?? null, 'int'),
        'inicio' => sanitize_input($_GET['inicio'] ?? null),
        'fin'    => sanitize_input($_GET['fin'] ?? null),
    ];
}

public function simulacroCreate()
{
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

    try {

        $nombre =
            sanitize_input(
                $data['nombre_simulacro'] ?? '',
                'string'
            );

        $fecha =
            sanitize_input(
                $data['fecha'] ?? '',
                'string'
            );

        if (empty($nombre) || empty($fecha)) {

            echo json_encode([
                'success' => false,
                'message' => 'Datos obligatorios'
            ]);

            return;
        }

        ProgramaAnualSimulacros::create([

            'id_estacion' =>
                $this->estacionId(),

            'nombre_simulacro' =>
                $nombre,

            'periodicidad' =>
                'Trimestral',

            'fecha' =>
                $fecha
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Programa agregado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function simulacroUpdate()
{
    header('Content-Type: application/json');

    try {

        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            );

        $id =
            (int)($data['id'] ?? 0);

        $registro =
            ProgramaAnualSimulacros::find($id);

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        $registro->update([

            'nombre_simulacro' =>
                sanitize_input(
                    $data['nombre_simulacro'],
                    'string'
                ),

            'fecha' =>
                sanitize_input(
                    $data['fecha'],
                    'string'
                )
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Programa actualizado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function simulacroDelete()
{
    header('Content-Type: application/json');

    try {

        $data = json_decode(file_get_contents('php://input'),true);
        $registro = ProgramaAnualSimulacros::find((int)$data['id']);

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            return;
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Programa eliminado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

//Personal

public function personalUsuarios($idPrograma)
{
    header('Content-Type: application/json');

    try {

        $ocupados =
            ProgramaAnualSimulacrosPersonal::where(
                'id_programa',
                $idPrograma
            )
            ->pluck('nombre');

        $usuarios =
            Usuario::where(
                'id_gas',
                $this->estacionId()
            )
            ->where(
                'estatus',
                0
            )
            ->whereNotIn(
                'nombre',
                $ocupados
            )
            ->orderBy('nombre')
            ->get([
                'nombre'
            ]);

        echo json_encode([

            'success' => true,

            'data' => $usuarios
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function personalGet($idPrograma)
{
    header('Content-Type: application/json');

    try {

        echo json_encode([

            'success' => true,

            'data' => ProgramaAnualSimulacrosPersonal::where(
                'id_programa',
                $idPrograma
            )
            ->orderBy('nombre')
            ->get()
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function personalCreate()
{
    header('Content-Type: application/json');

    try {

        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            );

        foreach (
            $data['usuarios']
            as $nombre
        ) {

            ProgramaAnualSimulacrosPersonal::firstOrCreate([

                'id_programa' =>
                    $data['id_programa'],

                'nombre' =>
                    $nombre
            ]);
        }

        echo json_encode([

            'success' => true,

            'message' => 'Personal agregado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function personalDelete()
{
    header('Content-Type: application/json');

    try {

        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            );

        ProgramaAnualSimulacrosPersonal::find(
            $data['id']
        )?->delete();

        echo json_encode([

            'success' => true,

            'message' => 'Personal eliminado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// Resumen

public function resumenGet($idPrograma)
{
    header('Content-Type: application/json');

    try {

        $resumen =
            ProgramaAnualSimulacrosResumen::where(
                'id_programa',
                $idPrograma
            )
            ->first();

        echo json_encode([
            'success' => true,
            'data' => $resumen
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

public function resumenCreate()
{
    header('Content-Type: application/json');

    try {

        $data = json_decode(file_get_contents('php://input'),true);

        $idPrograma = (int)($data['id_programa'] ?? 0 );

        $resumen =
            sanitize_input(
                $data['resumen']
                ?? '',
                'string'
            );

        if(empty($resumen)){
            echo json_encode([
                'success' => false,
                'message' => 'Todos los campos son obligatorios'
            ]);
            return;
        }

        ProgramaAnualSimulacrosResumen::updateOrCreate(

            [

                'id_programa' =>
                    $idPrograma
            ],

            [

                'resumen' =>
                    $resumen
            ]
        );

        echo json_encode([

            'success' => true,

            'message' =>
                'Resumen guardado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}

// Evaluacion

public function evaluacionCreate()
{
    header('Content-Type: application/json');

    try {

        $idPrograma =
            (int)(
                $_POST['id_programa']
                ?? 0
            );

        if(!$idPrograma){

            echo json_encode([

                'success' => false,

                'message' =>
                    'Programa inválido'
            ]);

            return;
        }

        if(
            empty($_FILES['archivo'])
            ||
            $_FILES['archivo']['error']
                !== UPLOAD_ERR_OK
        ){

            echo json_encode([

                'success' => false,

                'message' =>
                    'Debe seleccionar un PDF'
            ]);

            return;
        }

        $extension =
            strtolower(
                pathinfo(
                    $_FILES['archivo']['name'],
                    PATHINFO_EXTENSION
                )
            );

        if(
            $extension !== 'pdf'
        ){

            echo json_encode([

                'success' => false,

                'message' =>
                    'Solo se permiten PDF'
            ]);

            return;
        }

        $carpeta =
            __DIR__
            . '../../../public/uploads/archivos/protocolo/';

        if(
            !file_exists($carpeta)
        ){

            mkdir_safe(
                $carpeta,
                true
            );
        }

        $nombreArchivo =
            'EVALUACION-SIMULACRO-'
            .$idPrograma
            .'-'
            .time()
            .'.pdf';

        $rutaFisica =
            $carpeta
            .$nombreArchivo;

        move_uploaded_file(

            $_FILES['archivo']['tmp_name'],

            $rutaFisica
        );

        $rutaBd =
            'archivos/protocolo/'
            .$nombreArchivo;

        ProgramaAnualSimulacrosEvaluacion::updateOrCreate(

            [

                'id_programa' =>
                    $idPrograma
            ],

            [

                'archivo' =>
                    $rutaBd
            ]
        );

        echo json_encode([

            'success' => true,

            'message' =>
                'Evaluación guardada correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([

            'success' => false,

            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}

public function simulacroPdf(){

    [
    'year'   => $year,
    'mes'    => $mes,
    'inicio' => $inicio,
    'fin'    => $fin
    ] = $this->filtros();

    $estacion = Estacion::find($this->estacionId());
    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $simulacros = ProgramaAnualSimulacros::with([
        'personal',
        'resumen'
    ])
    ->where(
        'id_estacion',
        $this->estacionId()
    )

    ->when(
        !empty($inicio) && !empty($fin),
        fn ($q) => $q->whereBetween('fecha', [$inicio, $fin])
    )

    ->when(
        empty($inicio) && empty($fin) && $year,
        fn ($q) => $q->whereYear('fecha', $year)
    )

    ->when(
        empty($inicio) &&
        empty($fin) &&
        !empty($mes) &&
        $mes != 13,
        fn ($q) => $q->whereMonth('fecha', $mes)
    )

    ->orderBy('fecha')

    ->get();

    $html = '
    <!DOCTYPE html>
    <html>
    <head>

        <meta charset="UTF-8">

        <title>
            Programa anual de simulacros
        </title>

        <style>
    @page {margin: 0.5cm 1cm; font-family: Arial, Helvetica, sans-serif;}
    *,
    *::before,
    *::after {
    box-sizing: border-box;
    }

    html {
    font-family: sans-serif;
    line-height: 1.15;
    -webkit-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
    -ms-overflow-style: scrollbar;
    -webkit-tap-highlight-color: transparent;
    }

    @-ms-viewport {
    width: device-width;
    }


    body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    }

    .text-center {
    text-align: center !important;
    }
    .p-1 {
    padding: 0.25rem !important;
    }
    .mt-1 {
    margin-top: 0.25rem !important;
    }
    .mt-3 {
    margin-top: 1rem !important;
    }
    .mt-4 {
    margin-top: 1.5rem !important;
    }

    .mb-2,
    .my-2 {
    margin-bottom: 0.5rem !important;
    }

    table {
    border-collapse: collapse;
    }
    .table {
    width: 100%;
    max-width: 100%;
    margin-bottom: 10px;
    background-color: transparent;
    }

    .table th,
    .table td {
    padding: 0.30rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
    }

    .table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
    }

    .table tbody + tbody {
    border-top: 2px solid #dee2e6;
    }

    .table .table {
    background-color: #fff;
    }

    .table-sm th,
    .table-sm td {
    padding: 0.2rem;
    }

    .table-bordered {
    border: 1px solid #dee2e6;
    }

    .table-bordered th,
    .table-bordered td {
    border: 1px solid #dee2e6;
    }

    .table-bordered thead th,
    .table-bordered thead td {
    border-bottom-width: 2px;
    }
    .table-bordered {
    border: 1px solid #dee2e6;
    }

    .table-bordered th,
    .table-bordered td {
    border: 1px solid #dee2e6;
    }

    .table-bordered thead th,
    .table-bordered thead td {
    border-bottom-width: 2px;
    }
    .table-sm th,
    .table-sm td {
    padding: 0.2rem;
    }
    .align-middle {
    vertical-align: middle !important;
    }

    .border {
    border: 1px solid #dee2e6 !important;
    }

    .mt-3,
    .my-3 {
    margin-top: 1rem !important;
    }

    .p-3 {
    padding: 1rem !important;
    }

    .mb-3,
    .my-3 {
    margin-bottom: 1rem !important;
    }
    .text-right {
    text-align: right !important;
    }
    hr {
    margin-top: 1rem;
    margin-bottom: 1rem;
    border: 0;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    </style>
    </head>
    <body>

    <table class="table table-bordered">
        <tr>
            <td
                class="text-center align-middle">
                <img
                    src="' . $logo . '"
                    width="150">
            </td>

            <td
                colspan="2"
                class="text-center align-middle">

                <b>
                    Programa anual de simulacros
                </b>

            </td>

            <td
                class="text-center align-middle">

                <b>
                    Fo.ADMONGAS.016
                </b>

            </td>

        </tr>

        <tr>

            <td class="text-center">

                Realizado por:<br>
                Nelly Estrada Garcia

            </td>

            <td class="text-center">

                Revisado por:<br>
                Eduardo Galicia Flores

            </td>

            <td class="text-center">

                Autorizado por:<br>
                ' . e(
                    $estacion->apoderado_legal
                ) . '

            </td>

            <td class="text-center">

                Fecha de aprobación<br>
                01-oct-18

            </td>

        </tr>
    </table>
    <br>';


    foreach ($simulacros as $simulacro) {

    $html .= '

    <table
        class="table table-bordered"
        style="font-size:.9em;">

        <tbody>

            <tr>

                <td class="text-center">
                    <b>Nombre del simulacro</b>
                </td>

                <td class="text-center">
                    <b>Periodicidad</b>
                </td>

                <td class="text-center">
                    <b>Fecha</b>
                </td>

            </tr>

            <tr>

                <td class="text-center">'
                    . e($simulacro->nombre_simulacro) .
                '</td>

                <td class="text-center">'
                    . e($simulacro->periodicidad) .
                '</td>

                <td class="text-center">'
                    . formatearFecha(
                        $simulacro->fecha
                    ) .
                '</td>

            </tr>

        </tbody>

    </table>';

    $html .= '

    <table
        class="table table-bordered"
        style="font-size:.9em;">

        <tbody>

            <tr>

                <td class="text-center">

                    <b>
                        Personal que asiste
                    </b>

                </td>

            </tr>';

    if (
        $simulacro->personal->count()
        > 0
    ) {

        foreach (
            $simulacro->personal
            as $personal
        ) {

            $html .= '

            <tr>

                <td>'
                    . e($personal->nombre) .
                '</td>

            </tr>';
        }

    } else {

        $html .= '

        <tr>

            <td class="text-center">

                No se encontró personal

            </td>

        </tr>';
    }

    $html .= '

        </tbody>

    </table>';

    if ($simulacro->resumen) {

        $html .= '

        <div
            class="border"
            style="
                padding:15px;
                font-size:.9em;
                margin-bottom:10px;
            ">

            <b>
                Resumen
            </b>

            <div>'
                . $simulacro->resumen->resumen .
            '</div>

        </div>';
    }

    $html .= '<hr>';
}

    $html .= '</body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled',true);
    $options->set('defaultFont','Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream('Programa anual de simulacros.pdf',['Attachment' => true]);

}



}