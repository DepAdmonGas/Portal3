<?php 
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\RevisionResultados;

class RevisionResultadosController extends BaseController{
protected string $modulo = 'sasisopa';
public function index(){

        $title = '17. REVISIÓN DE RESULTADOS';

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
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/revisionresultados/index.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('revisionresultados/index', $data,'sasisopa');

    }

    public function datatable(){

    header('Content-Type: application/json');

    try {

        $registros = RevisionResultados::with([
            'usuario'
        ])
        ->where(
            'id_estacion',
            $this->estacionId()
        )
        ->orderByDesc('fecha_hora')
        ->get();

        $data = [];

        foreach ($registros as $item) {

            $data[] = [

                'id' => $item->id,

                'fecha' => $item->fecha_hora
                    ? $item->fecha_hora->format('Y-m-d')
                    : '',

                'fecha_larga' => $item->fecha_hora
                    ? formatearFecha(
                        $item->fecha_hora->format('Y-m-d')
                    )
                    : '',

                'usuario' =>
                    $item->usuario?->nombre,

                'archivo' =>
                    '/uploads/'.$item->archivo,

                'tiene_archivo' =>
                    !empty($item->archivo),
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

        if (!isset($_FILES['archivo'])) {

            throw new \Exception(
                'Debe seleccionar un PDF'
            );
        }

        $fecha = $_POST['fecha'];
        $archivo = $_FILES['archivo'];

        $nombreArchivo =
            $this->estacionId()
            . '-RESULTADOS-'
            . time()
            . '.pdf';

        $rutaFisica = __DIR__ . '../../../public/uploads/archivos/revision-resultados/' . $nombreArchivo;

       if (!move_uploaded_file(
                $archivo['tmp_name'],
                $rutaFisica
            )) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Error al subir archivo'
                ]);
                exit;
            }

        $revision =
            RevisionResultados::create([

                'id_estacion' =>
                    $this->estacionId(),

                'id_usuario' =>
                    $this->userId(),

                'fecha_hora' =>
                    $fecha . ' ' . date('H:i:s'),

                'archivo' =>
                    'archivos/revision-resultados/'
                    . $nombreArchivo
            ]);

        echo json_encode([
            'success' => true,
            'id' => $revision->id
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
    }

    public function update()
    {
        header('Content-Type: application/json');

        try {

            $revision =
                RevisionResultados::find(
                    $_POST['id']
                );

            if (!$revision) {

                throw new \Exception(
                    'Registro no encontrado'
                );
            }

            $revision->fecha_hora =
                $_POST['fecha']
                . ' '
                . date('H:i:s');

            if (isset($_FILES['archivo'])) {

                if (
                    !empty($revision->archivo)
                    &&
                    file_exists(
                        __DIR__ . '../../../public/uploads/'
                        . $revision->archivo
                    )
                ) {

                    unlink(
                        __DIR__ . '../../../public/uploads/'
                        . $revision->archivo
                    );
                }

                $nombreArchivo =
                    $revision->id
                    . '-RESULTADOS-'
                    . time()
                    . '.pdf';

                $rutaFisica =
                    __DIR__ . '../../../public/uploads/archivos/revision-resultados/'
                    . $nombreArchivo;

                move_uploaded_file(
                    $_FILES['archivo']['tmp_name'],
                    $rutaFisica
                );

                $revision->archivo =
                    'archivos/revision-resultados/'
                    . $nombreArchivo;
            }

            $revision->save();

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
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

            $revision = RevisionResultados::find(
                $data['id'] ?? 0
            );

            if (!$revision) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            // Eliminar archivo físico si existe
            if (
                !empty($revision->archivo) &&
                file_exists(
                    $_SERVER['DOCUMENT_ROOT'] . $revision->archivo
                )
            ) {

                unlink(
                    $_SERVER['DOCUMENT_ROOT'] . $revision->archivo
                );
            }

            $revision->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
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