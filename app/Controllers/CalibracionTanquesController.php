<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoTanque;
use App\Services\CalibracionEquipoService;

class CalibracionTanquesController extends BaseController
{
    protected string $modulo = 'sasisopa';

    public function index(int $id)
    {
        $title = 'Bitácora calibración de equipos (Tanques de almacenamiento)';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );
        Breadcrumb::add(
            'Calibración de Equipos',
            '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos'
        );
        Breadcrumb::add($title, '');

         $calibracion = CalibracionEquipo::query()
            ->with(['detalles','tanques.tanque'])
            ->findOrFail($id);

        $calibracion->unidad_verificacion =
            optional(
                $calibracion->detalles
                    ->firstWhere('categoria','Unidad de verificación')
            )->resultado ?? '';

        $calibracion->numero_acreditacion =
            optional(
                $calibracion->detalles
                    ->firstWhere('categoria','No. de acreditación')
            )->resultado ?? '';

        $calibracion->metodo_calibracion =
            optional(
                $calibracion->detalles
                    ->firstWhere('categoria','Método usado para la calibración')
            )->resultado ?? '';

        $calibracion->fecha_formateada =
            $calibracion->fecha &&
            $calibracion->fecha->year > 1900
                ? $calibracion->fecha->format('Y-m-d')
                : '';

        $calibracion->tanques
            ->each(function ($item) {

                $item->_original = [
                    'resultado1' => $item->resultado1,
                    'resultado2' => $item->resultado2
                ];

            });

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,

            'calibracion' => $calibracion,

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/controlactividadproceso/calibraciontanques.init.js?v=1.5'
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/calibracion-tanques',$data,'sasisopa');
    }

    public function update()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        $id = (int)($data['id'] ?? 0);
        $input = (int)($data['input'] ?? 0);
        $valor = sanitize_input($data['valor'] ?? '','string');

        try {

            $actualizado = $this->editarTanque($valor,$id,$input);
            echo json_encode(['success' => (bool)$actualizado]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function editarTanque($contenido,$id,$input) {

        return match ((int)$input) {
            1 => CalibracionEquipo::where(
                    'id',
                    $id
                )->update([
                    'fecha' => $contenido
                ]),

            2 => CalibracionEquipo::where(
                    'id',
                    $id
                )->update([
                    'hora' => $contenido
                ]),

            3 => CalibracionEquipoDetalle::where(
                    'id_calibracion',
                    $id
                )
                ->where(
                    'categoria',
                    'Unidad de verificación'
                )
                ->update([
                    'resultado' => $contenido
                ]),

            4 => CalibracionEquipoDetalle::where(
                    'id_calibracion',
                    $id
                )
                ->where(
                    'categoria',
                    'No. de acreditación'
                )
                ->update([
                    'resultado' => $contenido
                ]),

            5 => CalibracionEquipoDetalle::where(
                    'id_calibracion',
                    $id
                )
                ->where(
                    'categoria',
                    'Método usado para la calibración'
                )
                ->update([
                    'resultado' => $contenido
                ]),

            6 => CalibracionEquipo::where(
                    'id',
                    $id
                )->update([
                    'observaciones' => $contenido
                ]),

            7 => CalibracionEquipo::where(
                    'id',
                    $id
                )->update([
                    'responsable_verificacion'
                        => $contenido
                ]),

            8 => CalibracionEquipoTanque::where(
                    'id',
                    $id
                )->update([
                    'resultado1' => $contenido
                ]),

            9 => CalibracionEquipoTanque::where(
                    'id',
                    $id
                )->update([
                    'resultado2' => $contenido
                ]),

            default => false
        };
    }

    public function resultado(int $id)
    {
        header('Content-Type: application/json');
        $tanque = CalibracionEquipoTanque::findOrFail($id);

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $tanque->id,
                'resultados' => $tanque->resultados
            ]
        ]);

        exit;
    }

    public function uploadResultado()
    {
    header('Content-Type: application/json');

        if (!ModuloService::validaPermiso($this->modulo,'editar')) {

            echo json_encode([
                'success' => false,
                'message' =>'No tienes permiso'
            ]);

            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $file = $_FILES['documento'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID inválido'
            ]);
            exit;
        }

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {

            echo json_encode([
                'success' => false,
                'message' => 'Debe seleccionar un archivo'
            ]);

            exit;
        }

        try {

            $extension = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                throw new \Exception(
                    'Solo se permiten PDF'
                );
            }

            $carpeta = __DIR__ . '../../../public/uploads/archivos/calibracion/';

            if (!file_exists($carpeta)) {
                mkdir_safe($carpeta,true);
            }

            $nombreArchivo = 'RESULTADOS_' . $id . '_' . time() . '.pdf';
            $rutaDestino = $carpeta . $nombreArchivo;

            if (!move_uploaded_file($file['tmp_name'],$rutaDestino)) {
                throw new \Exception(
                    'No fue posible guardar el archivo'
                );
            }

            $tanque = CalibracionEquipoTanque::findOrFail($id);

            if (!empty($tanque->resultados)) {
                $anterior = $carpeta . $tanque->resultados;
                if (file_exists($anterior)) {
                    unlink($anterior);
                }
            }

            $tanque->update(['resultados' =>$nombreArchivo]);

            echo json_encode([
                'success' => true,
                'archivo' => $nombreArchivo,
                'message' => 'Archivo guardado correctamente'
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

    public function finalizar()
    {
        $data = json_decode(file_get_contents('php://input'),true);

        try {

            $service = new CalibracionEquipoService();
            $service->finalizar((int)$data['id']);
            echo json_encode(['success' => true]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}