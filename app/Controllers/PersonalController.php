<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Usuario;
use App\Models\Puestos;
use App\Models\Sasisopa\CursoCalendario;
use Carbon\Carbon;

use Illuminate\Database\Capsule\Manager as Capsule;
class PersonalController extends BaseController
{

protected string $modulo = 'sasisopa';

    public function index($categoria = null)
    {
        $title = 'PERSONAL';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        (!$categoria)?: Breadcrumb::add($categoria, '/' . mb_strtolower($categoria));        
        Breadcrumb::add($title, '');

        $usuario = Usuario::findOrFail($this->userId());

        $puestos = Puestos::query()
            ->where('estatus', 0);

        if ($usuario->id_puesto === 6) {

            $puestos->whereIn('tipo_puesto', [
                'Encargado',
                'Asistente Administrativo',
                'Mantenimiento',
                'Despachador',
                'Jefe de Piso',
                'Facturista',
                'Autolavado',
                'Intendencia',
            ]);

        }

        $descarga = '';
        if ($usuario->id_gas == 1) {
            $descarga = "FORMATO DE RENUNCIA INTERLOMAS.docx";
        } else if ($usuario->id_gas == 2) {
            $descarga = "FORMATO DE RENUNCIA PALO SOLO.docx";
        } else if ($usuario->id_gas == 3) {
            $descarga = "FORMATO DE RENUNCIA SAN AGUSTIN.docx";
        } else if ($usuario->id_gas == 4) {
            $descarga = "FORMATO DE RENUNCIA GASOMIRA.docx";
        } else if ($usuario->id_gas == 5) {
            $descarga = "FORMATO DE RENUNCIA VALLE.docx";
        } else if ($usuario->id_gas == 6) {
            $descarga = "FORMATO DE RENUNCIA ESMEGAS.docx";
        } else if ($usuario->id_gas == 7) {
            $descarga = "FORMATO DE RENUNCIA XOCHIMILCO.docx";
        }

        $puestos = $puestos
            ->orderBy('tipo_puesto')
            ->get(['id', 'tipo_puesto']);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'puestos' => $puestos,
            'renuncia' => $descarga,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/personal/index.datatable.init.js?v=1.3',
                '/js/personal/index.actions.init.js?v=1.3'
            ],
            'help' => true
        ];
        
        View::render('personal/index', $data,'sasisopa');
        
    }

    public function datatablePersonal(){

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $rows = Usuario::where('id_gas', $this->estacionId())
            ->with('puesto')
            ->orderBy('id')
            ->get();

        $data = [];

        foreach ($rows as $row) {

               $estatus = [
                "titulo" => '',
                "color_css" => '',
                "color_hexa" => ''
            ];

            if ($row->estatus == 1) {

                $estatus = [
                    "titulo" => 'Eliminado',
                    "color_css" => 'bg-danger',
                    "color_hexa" => '#E32702',
                    "estatus" => false
                ];

            } else {

                $estatus = [
                    "titulo" => 'Activo',
                    "color_css" => 'badge bg-success',
                    "color_hexa" => '#02E318',
                    "estatus" => true
                ];
            }

            $data[] = [
                "id" => $row->id,
                "nombre" => $row->nombre,
                "id_puesto" => $row->puesto->id,
                "puesto" => $row->puesto->tipo_puesto,
                "telefono" => $row->telefono,
                "fecha_ingreso" => $row->fecha_ingreso,
                "email" => $row->email,
                "usuario" => $row->usuario,
                "password" => $row->password,
                "estatus" => $estatus
            ];
        }

        echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);

        exit;

    }

    public function deletePersonal(){
    header('Content-Type: application/json');

    try {

        $data = json_decode(file_get_contents('php://input'), true);

        $idUsuario = (int) ($data['id'] ?? 0);

        $usuario = Usuario::find($idUsuario);

        if (!$usuario) {

            echo json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]);

            return;
        }

        Capsule::transaction(function () use ($usuario, $idUsuario) {

            $usuario->estatus = 1;
            $usuario->save();

            CursoCalendario::query()
                ->where('id_personal', $idUsuario)
                ->where(function ($query) {

                    $query->where('estado', 0)
                        ->orWhere('resultado', 0)
                        ->orWhere('fecha_real', '0000-00-00')
                        ->orWhereNull('fecha_real');

                })
                ->delete();

        });

        echo json_encode([
            'success' => true,
            'message' => 'Usuario activado correctamente'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
    }

    public function createPersonal(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            $idEstacion = $this->estacionId();

            Capsule::transaction(function () use ($data, $idEstacion) {

                $usuario = Usuario::create([
                    'nombre' => trim($data['nombre']),
                    'email' => trim($data['email']),
                    'telefono' => trim($data['telefono']),
                    'id_gas' => $idEstacion,
                    'id_puesto' => (int) $data['id_puesto'],
                    'usuario' => trim($data['usuario']),
                    'password' => $data['password'],
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'bitacora_app' => 0,
                    'estatus' => 0,
                ]);

                $this->crearCursosInduccion(
                    $usuario->id,
                    $idEstacion
                );

            });

            echo json_encode([
                'success' => true,
                'message' => 'Usuario agregado correctamente.'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }

        exit;
    }

    private function crearCursosInduccion(int $idUsuario, int $idEstacion): void
    {
        $cursos = [
            ['dias' => 1, 'tema' => 1],
            ['dias' => 2, 'tema' => 2],
            ['dias' => 3, 'tema' => 24],
            ['dias' => 4, 'tema' => 25],
            ['dias' => 5, 'tema' => 26],
        ];

        foreach ($cursos as $curso) {

            CursoCalendario::create([
                'fecha_programada' => Carbon::today()
                    ->addDays($curso['dias'])
                    ->toDateString(),

                'fecha_real' => '',

                'id_estacion' => $idEstacion,

                'id_personal' => $idUsuario,

                'id_tema' => $curso['tema'],

                'resultado' => 0,

                'observaciones' => 'Inducción',

                'estado' => 0,
            ]);

        }
    }

    public function updatePersonal(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $data = json_decode(file_get_contents('php://input'), true);

        Capsule::transaction(function () use ($data) {

            $usuario = Usuario::findOrFail((int)$data['id']);

            $usuario->update([
                'nombre' => trim($data['nombre']),
                'email' => trim($data['email']),
                'telefono' => trim($data['telefono']),
                'id_puesto' => (int) $data['id_puesto'],
                'usuario' => trim($data['usuario']),
                'fecha_ingreso' => $data['fecha_ingreso'],
            ]);

            // Solo actualizar password si viene llena
            if (!empty($data['password'])) {
                $usuario->update([
                    'password' => $data['password']
                ]);
            }

        });

        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado correctamente'
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