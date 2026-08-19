<?php 
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Usuario;
use App\Models\UsuariosFirmaBitacora;
class ConfiguracionBitacoraController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

     $title = 'Configuración de bitácoras';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS', '/sasisopa/control-actividades-procesos');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                 '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/configuracionbitacora/index.datatable.init.js?v=' . time(),
                '/js/configuracionbitacora/index.action.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('configuracionbitacora/index', $data,'sasisopa');
        
    }

    public function datatableConfiguracionBitacora(){
    
       $idEstacion = $this->estacionId();

        $usuarios = UsuariosFirmaBitacora::query()
            ->with([
                'usuario:id,nombre,id_puesto,id_gas',
                'usuario.puesto:id,tipo_puesto'
            ])
            ->where('estado', 1)
            ->whereHas('usuario', function ($query) use ($idEstacion) {
                $query->where('id_gas', $idEstacion);
            })
            ->get();

        $data = $usuarios->map(function ($item) {

       $categoria = trim(strtoupper($item->categoria));
       $categoriaBadge = match ($categoria) {

        'MPC' => '
            <span class="badge text-bg-primary">
                Mantenimiento Preventivo y Correctivo
            </span>
        ',

        'RDP' => '
            <span class="badge text-bg-secondary">
                Recepción y Descarga del Producto
            </span>
        ',

        default => '
            <span class="badge text-bg-secondary">
                '.$item->categoria.'
            </span>
        '
    };


            return [
                'id_firma' => $item->id,
                'id_usuario' => $item->usuario->id,
                'nombre' => $item->usuario->nombre,
                'puesto' => $item->usuario->puesto->tipo_puesto ?? '',
                'categoria' => $item->categoria == 'MPC' ? 'Mantenimiento Preventivo y Correctivo' : 'Recepción y Descarga del Producto',
                'categoria_badge' => $categoriaBadge
            ];
        });

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'editar' =>  ModuloService::validaPermiso($this->modulo, 'editar'),
                'eliminar' =>  ModuloService::validaPermiso($this->modulo, 'eliminar')
            ]
        ]);
    }

    public function getTrabajadorAutorizado()
    {
        header('Content-Type: application/json');

        $idEstacion = $this->estacionId();

        $usuarios = Usuario::query()

            ->with([
                'puesto:id,tipo_puesto'
            ])

            ->where('id_gas', $idEstacion)

            ->activo()

            ->withCount([
                'firmasBitacora as total_categorias' => function ($query) {
                    $query->where('estado', 1);
                }
            ])

            ->having('total_categorias', '<', 2)

            ->orderBy('nombre')

            ->get([
                'id',
                'nombre',
                'id_puesto'
            ]);

        $data = $usuarios->map(function ($usuario) {

            // =====================================================
            // NORMALIZAR CATEGORIAS
            // =====================================================

            $categorias = UsuariosFirmaBitacora::query()
                ->where('id_usuario', $usuario->id)
                ->where('estado', 1)
                ->pluck('categoria')
                ->map(function ($categoria) {

                    return trim(
                        strtoupper($categoria)
                    );

                })
                ->unique()
                ->values()
                ->toArray();

            $faltantes = [];

            // =====================================================
            // VALIDAR MPC
            // =====================================================

            if (!in_array('MPC', $categorias, true)) {

                $faltantes[] = [
                    'codigo' => 'MPC',
                    'nombre' => 'Mantenimiento Preventivo y Correctivo'
                ];
            }

            // =====================================================
            // VALIDAR RDP
            // =====================================================

            if (!in_array('RDP', $categorias, true)) {

                $faltantes[] = [
                    'codigo' => 'RDP',
                    'nombre' => 'Recepción y Descarga del Producto'
                ];
            }

            return [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'puesto' => $usuario->puesto->tipo_puesto ?? '',
                'categorias_actuales' => $categorias,
                'faltantes' => $faltantes
            ];
        });

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    public function createTrabajadorAutorizado(){

    header('Content-Type: application/json');

    try {

        $data = json_decode(file_get_contents('php://input'),true);

        $idUsuario = sanitize_input($data['id_usuario'] ?? null,'int');
        $categorias = $data['categorias'] ?? [];

        foreach ($categorias as $categoria) {

            $existe = UsuariosFirmaBitacora::query()
                ->where('id_usuario', $idUsuario)
                ->where('categoria', $categoria)
                ->where('estado', 1)
                ->exists();

            if (!$existe) {

                UsuariosFirmaBitacora::create([
                    'id_estacion' => $this->estacionId(),
                    'id_usuario' => $idUsuario,
                    'categoria' => $categoria,
                    'fechainicio' => date('Y-m-d H:i:s'),
                    'estado' => 1
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Personal autorizado agregado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    }

    public function deleteTrabajadorAutorizado(){

        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'),true);

            $idFirma = sanitize_input($data['id'] ?? null,'int');
            $comentario = sanitize_input($data['comentario'] ?? '','string');
            $hoy = date('Y-m-d H:i:s');

            if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar'
                ]);
                return;
            }

            $firma = UsuariosFirmaBitacora::find($idFirma);

            if (!$firma) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);
                return;
            }

            $firma->update([
                'fechatermino' => $hoy,
                'comentario' => $comentario,
                'estado' => 0
            ]);

        
            echo json_encode([
                'success' => true,
                'message' => 'Firma eliminada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

}