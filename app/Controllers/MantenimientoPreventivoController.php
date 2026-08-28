<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Estacion;
use App\Models\Sasisopa\MantenimientoLista;
use App\Models\Sasisopa\MantenimientoVerificar;
use App\Models\Sasisopa\MantenimientoVerificarEvidencia;
use App\Services\MantenimientoPreventivoService;
use Dompdf\Dompdf;
use Dompdf\Options;
class MantenimientoPreventivoController extends BaseController{

    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(){

    $title = 'Mantenimiento Preventivo';

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

        $idEstacion = $this->estacionModulo();

        $data = [

            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/controlactividadproceso/mantenimientopreventivo.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/mantenimientopreventivo.action.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/mantenimiento-preventivo',$data,'sasisopa');
        
    }

    public function datatable(){

    $year = $_GET['year'] ?? null;
    $mes = $_GET['mes'] ?? null;
    $id_equipo = $_GET['id_equipo'] ?? null;

    $data = MantenimientoVerificar::with('mantenimientoLista:id,detalle')

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        // FILTRO POR AÑO
        ->when($year, function ($query) use ($year) {

            $query->whereYear(
                'fechacreacion',
                $year
            );
        })

        // FILTRO POR MES (OPCIONAL)
        ->when($mes, function ($query) use ($mes) {

            $query->whereMonth(
                'fechacreacion',
                $mes
            );
        })

        // FILTRO POR EQUIPO (OPCIONAL)
        ->when($id_equipo, function ($query) use ($id_equipo) {

            $query->where(
                'id_equipo',
                $id_equipo
            );
        })

        ->orderBy('estado', 'ASC')

        ->get()

        ->map(function ($item) {

            return [
                'id' => $item->id,
                'folio' => str_pad(
                    $item->folio,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'fechacreacion' => $item->fechacreacion
                    ? $item->fechacreacion->format('Y-m-d')
                    : '',
                'fechacreacion_larga' => formatearFecha($item->fechacreacion),
                'horacreacion' => $item->horacreacion->format('H:i a'),
                'estado' => $item->estado,
                'id_equipo' => $item->id_equipo,
                'detalle' => $item->mantenimientoLista->detalle ?? '',
            ];
        });

    echo json_encode([
        "data" => $data
    ]);

    exit;
        
    }

    public function get()
    {
        try {

            $equipos = MantenimientoLista::query()
                ->orderBy('num_lista')
                ->get([
                    'id',
                    'detalle'
                ]);

            echo json_encode(
                $equipos
            );

        } catch (\Throwable $e) {

            echo json_encode([]);
        }
    }

    public function pdf(): void
    {
        $idEstacion = $this->estacionModulo();
        if (!$idEstacion) {
            exit('Selecciona una estación para generar el PDF');
        }
        $service = new MantenimientoPreventivoService($idEstacion);
        $service->generarPdf();
        exit;
    }   

    public function evidencias(int $id): void
    {
        $data = MantenimientoVerificarEvidencia::where(
            'id_mantenimiento',
            $id
        )->get();

        echo json_encode(['data' => $data]);
    }

    public function createEvidencia(): void
    {
        header('Content-Type: application/json');

        try {
            $id_mantenimiento = sanitize_input(
                $_POST['id_mantenimiento'] ?? null,
                'int'
            );

            if (!$id_mantenimiento) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Mantenimiento inválido'
                ]);
                return;
            }

            if (!isset($_FILES['imagenes'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se recibieron imágenes'
                ]);
                return;
            }

            $files = $_FILES['imagenes'];
            $directory = 'uploads/archivos/mantenimiento';

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $guardadas = 0;

            foreach ($files['tmp_name'] as $key => $tmp_name) {
                if (empty($tmp_name)) continue;

                $name = $files['name'][$key];
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) continue;

                $mime = mime_content_type($tmp_name);
                if (!str_starts_with($mime, 'image/')) continue;

                $maxWidth = 1600;
                $quality = 80;

                [$width, $height] = getimagesize($tmp_name);

                if ($width > $maxWidth) {
                    $newWidth = $maxWidth;
                    $newHeight = intval(($height * $newWidth) / $width);
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                }

                $source = match ($extension) {
                    'jpg', 'jpeg' => imagecreatefromjpeg($tmp_name),
                    'png' => imagecreatefrompng($tmp_name),
                    'webp' => imagecreatefromwebp($tmp_name),
                    default => null,
                };

                if (!$source) continue;

                $optimized = imagecreatetruecolor($newWidth, $newHeight);

                if (in_array($extension, ['png', 'webp'])) {
                    imagealphablending($optimized, false);
                    imagesavealpha($optimized, true);
                }

                imagecopyresampled(
                    $optimized, $source,
                    0, 0, 0, 0,
                    $newWidth, $newHeight,
                    $width, $height
                );

                $nuevoNombre = 'MANTENIMIENTOP-'
                    . $id_mantenimiento
                    . '-'
                    . uniqid()
                    . '.webp';

                $ruta = $directory . DIRECTORY_SEPARATOR . $nuevoNombre;

                $saved = imagewebp($optimized, $ruta, $quality);

                imagedestroy($source);
                imagedestroy($optimized);

                if ($saved) {
                    $url = $_ENV['APP_URL']
                        . '/uploads/archivos/mantenimiento/'
                        . $nuevoNombre;

                    MantenimientoVerificarEvidencia::create([
                        'id_mantenimiento' => $id_mantenimiento,
                        'url' => $url,
                        'nombre' => $nuevoNombre,
                    ]);

                    $guardadas++;
                }
            }

            if ($guardadas <= 0) {
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

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteEvidencia(): void
    {
        header('Content-Type: application/json');

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $registro = MantenimientoVerificarEvidencia::find($data['id']);

            if (!$registro) {
                echo json_encode([
                    'success' => false,
                    'message' => 'La evidencia no existe'
                ]);
                return;
            }

            $path = 'uploads/archivos/mantenimiento/'
                . $registro->nombre;

            if (file_exists($path)) {
                unlink($path);
            }

            $registro->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Evidencia eliminada correctamente'
            ]);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar evidencia'
            ]);
        }
    }

}