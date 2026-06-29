<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Puestos;
use App\Models\Sasisopa\Comunicado;
use App\Models\Sasisopa\Noticia;
use App\Models\Sasisopa\ComunicacionIE;

use Illuminate\Database\Capsule\Manager as Capsule;

class ComunicadosController extends BaseController{
    protected string $modulo = 'sasisopa';

    public function index(){

    $title = 'COMUNICADOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' =>[ 
                 '/libs/select2/dist/css/select2.min.css',
                 '/css/select2-modal.css?v=1.0.1'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sasisopa/comunicados.actions.init.js?v=1.3'
            ],
            'help' => false
        ];
        
        View::render('sasisopa/comunicados', $data,'sasisopa');
        
    }


public function datatable()
{
    header('Content-Type: application/json');

    try {

        $registros = Comunicado::where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('fecha')
            ->get();

        // Obtener todos los puestos una sola vez
        $puestos = Puestos::pluck(
            'tipo_puesto',
            'id'
        );

        $data = [];

        foreach ($registros as $item) {

            $ids = collect(
                explode(',', $item->dirigidoa)
            )
            ->filter()
            ->map(fn($id) => (int)$id);

            $dirigido = [];

            foreach ($ids as $id) {

                if(isset($puestos[$id])){

                    $dirigido[] = [

                        'id' => $id,
                        'puesto' => $puestos[$id]

                    ];

                }

            }

            $data[] = [

                'id' => $item->id,

                'id_comunicado' => $item->id_comunicado,

                'fecha' => $item->fecha
                    ? $item->fecha->format('Y-m-d')
                    : '',

                'fecha_larga' => $item->fecha
                    ? formatearFecha(
                        $item->fecha->format('Y-m-d')
                    )
                    : '',

                'tema' => $item->tema,

                'detalle' => $item->detalle,

                'detalle_corto' =>
                    mb_strlen($item->detalle) > 50
                        ? mb_substr($item->detalle,0,50).'...'
                        : $item->detalle,

                'archivo' => $item->archivo
                    ? '/uploads/'.$item->archivo
                    : null,

                'dirigidoa' => $dirigido

            ];
        }

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


public function create()
{
    header('Content-Type: application/json');

    try {

        Capsule::transaction(function () {

            $dirigidoa = json_decode(
                $_POST['dirigidoa'],
                true
            );

            $ultimo =
                Comunicado::where(
                    'id_estacion',
                    $this->estacionId()
                )
                ->max('id_comunicado');

            $idComunicado =
                ($ultimo ?? 0) + 1;

            $archivo = '';

            if (
                isset($_FILES['archivo']) &&
                $_FILES['archivo']['error'] == 0
            ) {

                $nombre =
                    time() .
                    '-' .
                    basename(
                        $_FILES['archivo']['name']
                    );

                $ruta = __DIR__ . '../../../public/uploads/archivos/comunicados/';

                if(!is_dir($ruta)){
                    mkdir(
                        $ruta,
                        0777,
                        true
                    );
                }

                move_uploaded_file(
                    $_FILES['archivo']['tmp_name'],
                    $ruta.$nombre
                );

                $archivo =
                    'archivos/comunicados/'.$nombre;
            }

            $comunicado = Comunicado::create([

                'id_estacion'   => $this->estacionId(),
                'id_comunicado' => $idComunicado,
                'id_usuario'    => $this->userId(),
                'fecha'         => date('Y-m-d'),
                'tema'          => $_POST['tema'],
                'detalle'       => $_POST['detalle'],
                'dirigidoa'     => implode(',', $dirigidoa),
                'archivo'       => $archivo

            ]);


            $url =
                'comunicados/comunicado-' .
                $this->estacionId() .
                '-' .
                $idComunicado;

            foreach($dirigidoa as $idPuesto){

                $usuarios =
                    Usuario::where(
                        'id_puesto',
                        $idPuesto
                    )
                    ->where(
                        'id',
                        '!=',
                        $this->userId()
                    )
                    ->get();

                foreach($usuarios as $usuario){

                    Noticia::create([

                        'id_usuario' =>
                            $usuario->id,

                        'titulo' =>
                            $_POST['tema'],

                        'detalle' =>
                            'Tienes un nuevo comunicado',

                        'fecha_hora' =>
                            date('Y-m-d H:i:s'),

                        'url' =>
                            $url,

                        'estado' =>
                            0

                    ]);

                }

            }

            $ultimoCom =
                ComunicacionIE::where(
                    'id_estacion',
                    $this->estacionId()
                )
                ->max('no_comunicacion');

            ComunicacionIE::create([

                'id_estacion' =>
                    $this->estacionId(),

                'no_comunicacion' =>
                    ($ultimoCom ?? 0) + 1,

                'fecha' =>
                    date('Y-m-d'),

                'tema' =>
                    $_POST['tema'],

                'detalle' =>
                    $_POST['detalle'],

                'encargado_comunicacion' =>
                    $this->userId(),

                'tipo_comunicacion' =>
                    'INTERNA',

                'material' =>
                    'Portal AdmonGas',

                'seguimiento' =>
                    '',

                'url' =>
                    $url

            ]);

        });

        echo json_encode([
            'success'=>true,
            'message'=> 'Comunicado agregado correctamente'
        ]);

    } catch(\Throwable $e){

        echo json_encode([
            'success'=>false,
            'message'=>$e->getMessage()
        ]);

    }

    exit;
}

public function delete()
{
    header('Content-Type: application/json');

    try {

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        Capsule::transaction(function () use ($data) {

            $comunicado = Comunicado::findOrFail(
                $data['id']
            );

            // Eliminar archivo físico
            if (
                !empty($comunicado->archivo) &&
                file_exists(
                    __DIR__ . '../../../public/uploads/' . $comunicado->archivo
                )
            ) {

                unlink(
                    __DIR__ . '../../../public/uploads/' . $comunicado->archivo
                );
            }

            // Eliminar registro relacionado (opcional)
            ComunicacionIE::where(
                'url',
                'comunicados/comunicado-' .
                $comunicado->id_estacion .
                '-' .
                $comunicado->id_comunicado
            )->delete();

            // Eliminar comunicado
            $comunicado->delete();

        });

        echo json_encode([
            'success' => true,
            'message' => 'Comunicado eliminado correctamente.'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}
}