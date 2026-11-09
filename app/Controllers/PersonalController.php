<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Core\PasswordValidator;
use App\Services\ModuloService;
use App\Services\ModuleStationService;

use App\Models\Usuario;
use App\Models\Puestos;
use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sasisopa\CursoCalendario;
use Carbon\Carbon;

use Illuminate\Database\Capsule\Manager as Capsule;
use Dompdf\Dompdf;
use Dompdf\Options;

class PersonalController extends BaseController
{

    protected string $modulo = 'sasisopa';

    private function moduleKey(?array $body = null): string
    {
        $module = $_GET['module']
            ?? $body['module']
            ?? $_POST['module']
            ?? 'sasisopa';

        return in_array($module, ['sasisopa', 'sgm'], true)
            ? $module
            : 'sasisopa';
    }

    public function index($categoria = null)
    {
        $title = 'PERSONAL';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        (!$categoria) ?: Breadcrumb::add($categoria, '/' . mb_strtolower($categoria));
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
        // ===== Multiestación: estación del contexto del módulo (SASISOPA / SGM) =====
        $isSasisopa = $categoria === 'SASISOPA';
        $isSgm = $categoria === 'SGM';

        $moduleStationKey = $isSasisopa
            ? 'sasisopa'
            : ($isSgm ? 'sgm' : null);

        if ($moduleStationKey) {

            $idEstacion = (int) (
                ModuleStationService::getContext($moduleStationKey)['id_estacion']
                ?? 0
            );

        } else {

            $idEstacion = (int) $this->estacionId();
        }

        $estacionRenuncia = $idEstacion ?: (int) $usuario->id_gas;

        if ($estacionRenuncia == 1) {
            $descarga = "FORMATO DE RENUNCIA INTERLOMAS.docx";
        } else if ($estacionRenuncia == 2) {
            $descarga = "FORMATO DE RENUNCIA PALO SOLO.docx";
        } else if ($estacionRenuncia == 3) {
            $descarga = "FORMATO DE RENUNCIA SAN AGUSTIN.docx";
        } else if ($estacionRenuncia == 4) {
            $descarga = "FORMATO DE RENUNCIA GASOMIRA.docx";
        } else if ($estacionRenuncia == 5) {
            $descarga = "FORMATO DE RENUNCIA VALLE.docx";
        } else if ($estacionRenuncia == 6) {
            $descarga = "FORMATO DE RENUNCIA ESMEGAS.docx";
        } else if ($estacionRenuncia == 7) {
            $descarga = "FORMATO DE RENUNCIA XOCHIMILCO.docx";
        }

        $layout = match ($categoria) {
            'SASISOPA' => 'sasisopa',
            'SGM'      => 'sgm',
            default    => 'main',
        };

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
            'layout' => $layout,
            'estacionId' => $idEstacion,
            'moduleStationKey' => $moduleStationKey,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => ($isSasisopa || $isSgm) ? [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',

                '/js/personal/index.datatable.init.js?v=1.3.0',
                '/js/personal/index.actions.init.js?v=1.7.0',
                '/js/core/module-station-selector.js?v=' . time()

            ] : [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',

                '/js/personal/index.datatable.init.js?v=1.3.0',
                '/js/personal/index.actions.init.js?v=1.7.0'

            ],
            'help' => true
        ];

        View::render('personal/index', $data, $layout);
    }

    public function datatablePersonal()
    {

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');


        $usuario = Usuario::findOrFail($this->userId());

        $idEstacion = (int) (ModuleStationService::getContext($this->moduleKey())['id_estacion'] ?? $this->estacionId());

        $rows = Usuario::query()
            ->where('id_gas', $idEstacion)
            ->when($idEstacion == 8, function ($query) use ($usuario) {

                if (in_array($usuario->id_puesto, [13, 14])) {
                    $query->whereIn('id_puesto', [13, 14]);
                } else {
                    $query->where('id_puesto', $usuario->id_puesto);
                }
            })
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
                "estatus" => $estatus,
                "responsabilidad_sgm" => $row->responsabilidad_sgm,
            ];
        }

        JsonResponse::custom([
            "id_gas" => $idEstacion,
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);

        exit;
    }

    public function deletePersonal()
    {
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

            $password = is_string($data['password'] ?? null)
                ? $data['password']
                : '';
            $passwordConfirmacion = is_string($data['password_confirmacion'] ?? null)
                ? $data['password_confirmacion']
                : '';
            $nombreUsuario = trim((string) ($data['usuario'] ?? ''));
            $longitudUsuario = function_exists('mb_strlen')
                ? mb_strlen($nombreUsuario, 'UTF-8')
                : strlen($nombreUsuario);
            $erroresUsuario = [];

            if (
                $longitudUsuario < 3
                || $longitudUsuario > 50
                || !preg_match('/^[\pL\pN._-]+$/u', $nombreUsuario)
            ) {
                $erroresUsuario['usuario'] = 'Escribe un usuario de 3 a 50 caracteres; utiliza letras, números, punto, guion o guion bajo.';
            } elseif (Usuario::query()->where('usuario', $nombreUsuario)->exists()) {
                $erroresUsuario['usuario'] = 'Ese nombre de usuario ya está registrado.';
            }

            if ($erroresUsuario) {
                JsonResponse::validation('Revisa los datos señalados.', $erroresUsuario);
            }

            $validacionPassword = PasswordValidator::validate($password);
            $erroresPassword = $validacionPassword['errors'];
            if (($validacionPassword['score'] ?? 0) <= 80 && !$erroresPassword) {
                $erroresPassword[] = 'La contraseña debe alcanzar el nivel Muy fuerte.';
            }

            if (!$validacionPassword['valid'] || ($validacionPassword['score'] ?? 0) <= 80) {
                JsonResponse::validation(
                    'La contraseña debe alcanzar el nivel Muy fuerte.',
                    ['password' => $erroresPassword]
                );
            }

            if ($password !== $passwordConfirmacion) {
                JsonResponse::validation(
                    'La confirmación de la contraseña no coincide.',
                    ['password_confirmacion' => 'Confirma la contraseña exactamente como la escribiste.']
                );
            }

            $idEstacion = (int) (ModuleStationService::getContext($this->moduleKey($data ?? []))['id_estacion'] ?? $this->estacionId());

            Capsule::transaction(function () use ($data, $idEstacion, $password, $nombreUsuario) {

                $usuario = Usuario::create([
                    'nombre' => trim($data['nombre']),
                    'email' => trim($data['email']),
                    'telefono' => trim($data['telefono']),
                    'id_gas' => $idEstacion,
                    'id_puesto' => (int) $data['id_puesto'],
                    'usuario' => $nombreUsuario,
                    'password' => $password,
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

            $idUsuario = (int) ($data['id'] ?? 0);
            $usuarioExistente = Usuario::query()->findOrFail($idUsuario);
            $password = is_string($data['password'] ?? null)
                ? $data['password']
                : '';
            $nombreUsuario = trim((string) ($data['usuario'] ?? ''));
            $longitudUsuario = function_exists('mb_strlen')
                ? mb_strlen($nombreUsuario, 'UTF-8')
                : strlen($nombreUsuario);
            $erroresUsuario = [];

            if (
                $longitudUsuario < 3
                || $longitudUsuario > 50
                || !preg_match('/^[\pL\pN._-]+$/u', $nombreUsuario)
            ) {
                $erroresUsuario['usuario'] = 'Escribe un usuario de 3 a 50 caracteres; utiliza letras, números, punto, guion o guion bajo.';
            } elseif (Usuario::query()->where('usuario', $nombreUsuario)->where('id', '!=', $idUsuario)->exists()) {
                $erroresUsuario['usuario'] = 'Ese nombre de usuario ya está registrado.';
            }

            if ($erroresUsuario) {
                JsonResponse::validation('Revisa los datos señalados.', $erroresUsuario);
            }

            if ($password !== '' && $password !== (string) $usuarioExistente->password) {
                $validacionPassword = PasswordValidator::validate($password);
                $erroresPassword = $validacionPassword['errors'];
                if (($validacionPassword['score'] ?? 0) <= 80 && !$erroresPassword) {
                    $erroresPassword[] = 'La contraseña debe alcanzar el nivel Muy fuerte.';
                }
                if (!$validacionPassword['valid'] || ($validacionPassword['score'] ?? 0) <= 80) {
                    JsonResponse::validation(
                        'La contraseña debe alcanzar el nivel Muy fuerte.',
                        ['password' => $erroresPassword]
                    );
                }

                $passwordConfirmacion = is_string($data['password_confirmacion'] ?? null)
                    ? $data['password_confirmacion']
                    : '';
                if ($password !== $passwordConfirmacion) {
                    JsonResponse::validation(
                        'La confirmación de la contraseña no coincide.',
                        ['password_confirmacion' => 'Confirma la contraseña exactamente como la escribiste.']
                    );
                }
            }

            Capsule::transaction(function () use ($data, $password, $nombreUsuario) {

                $usuario = Usuario::findOrFail((int)$data['id']);

                $usuario->update([
                    'nombre' => trim($data['nombre']),
                    'email' => trim($data['email']),
                    'telefono' => trim($data['telefono']),
                    'id_puesto' => (int) $data['id_puesto'],
                    'usuario' => $nombreUsuario,
                    'fecha_ingreso' => $data['fecha_ingreso'],
                ]);

                // Solo actualizar password si viene llena
                if ($password !== '') {
                    $usuario->update([
                        'password' => $password
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

    public function sgmPdf(): void
    {
        $estacionId = (int) (ModuleStationService::getContext('sgm')['id_estacion'] ?? $this->estacionId());

        $autorizado = Autorizado::with('usuario')
            ->where('estado', 1)
            ->whereHas('usuario', fn($q) => $q->where('id_gas', $estacionId))
            ->first();

        $usuarios = Usuario::with(['puesto', 'ultimaExperiencia'])
            ->where('id_gas', $estacionId)
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get();

        $estacion = Estacion::findOrFail($estacionId);

        $rows = '';

        foreach ($usuarios as $usuario) {

            $fechaIngreso = optional($usuario->ultimaExperiencia?->periodo_inicio)
                ?->format('d-m-Y') ?? '';

            $rows .= '
                <tr>
                <td class="text-center">' . $usuario->id . '</td>
                <td class="text-center">' . $usuario->nombre . '</td>
                <td class="text-center">Activo</td>
                <td class="text-center">' . formatearFecha($fechaIngreso) . '</td>
                <td class="text-center">' . $usuario->puesto?->tipo_puesto . '</td>
                <td class="text-center">' . $usuario->responsabilidad_sgm . '</td>
                </tr>
            ';
        }

        $realizadoPor = $autorizado?->usuario?->nombre ?? '';

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="UTF-8">
        <title>Lista de personal</title>
        <style>
        ' . $css . '
        </style>
        </head>

        <body>

        <table class="table table-sm table-striped table-bordered">
            <tr>
                <td rowspan="2" align="center">' . $estacion->razonsocial . '</td>
                <td rowspan="2" align="center"><strong>Lista de personal</strong></td>
                <td align="center"><strong>Fecha de autorización: 01-01-2024</strong></td>
            </tr>
            <tr>
                <td align="center">Fo.SGM.008</td>
            </tr>
            <tr>
                <td align="center">Realizado por:<br>' . $realizadoPor . '</td>
                <td align="center">Revisado por:<br>Eduardo Galicia Flores</td>
                <td align="center">Autorizado por:<br>' . $estacion->apoderado_legal . '</td>
            </tr>
        </table>

        <br>

        <table class="table table-sm table-striped table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nombre</th>
                    <th>Estatus</th>
                    <th>Fecha de ingreso</th>
                    <th>Puesto</th>
                    <th>Grado de responsabilidad respecto al SGM</th>
                </tr>
            </thead>
            <tbody>
                ' . $rows . '
            </tbody>
        </table>

        </body>
        </html>
        ';

        $pdf = new Dompdf();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream('Lista de personal.pdf', ['Attachment' => false]);
    }
}
