<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

use App\Models\Usuario;
use App\Models\Estacion;
use App\Models\Puestos;

class UsuarioController extends BaseController
{
    /**
     * Vista principal de usuarios.
     */
    public function index(): void
    {
        if (!$this->tieneAccesoUsuarios()) {
            header('Location: /home');
            exit;
        }

        $idestacion = isset($_GET['idEstacion'])
            && is_numeric($_GET['idEstacion'])
            ? (int) $_GET['idEstacion']
            : null;

        /**
         * Si llega una estación por GET,
         * validamos que realmente exista.
         */
        if ($idestacion) {

            $existe = Estacion::where('id', $idestacion)
                ->exists();

            if (!$existe) {
                $idestacion = null;
            }
        }

        /**
         * Catálogo de estaciones.
         */
        $estaciones = Estacion::select([
            'id',
            'numlista',
            'nombre',
            'razonsocial',
            'estatus'
        ])
            ->orderBy('numlista')
            ->get();

        /**
         * Catálogo de puestos.
         */
        $puestos = Puestos::select([
            'id',
            'tipo_puesto',
            'estatus'
        ])
            ->orderBy('tipo_puesto')
            ->get();

        $title = 'Usuarios';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,

            'idestacion' => $idestacion,

            'estaciones' => $estaciones,

            'puestos' => $puestos,

            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',

                '/js/usuarios/datatable.init.js?v=' . time(),

                '/js/usuarios/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'usuario/index',
            $data,
            'main'
        );
    }


    /**
     * Datos del DataTable.
     */
    public function datatableUsuarios(): void
    {
        if (!$this->tieneAccesoUsuarios()) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No autorizado.',
                'data' => []
            ]);

            return;
        }

        try {

            $idestacion = isset($_GET['idestacion'])
                && is_numeric($_GET['idestacion'])
                ? (int) $_GET['idestacion']
                : null;

            $usuarios = Usuario::select([
                'id',
                'nombre',
                'email',
                'telefono',
                'id_gas',
                'id_puesto',
                'usuario',
                'estatus'
            ])
                ->with([
                    'estacion:id,razonsocial',
                    'puesto:id,tipo_puesto'
                ])
                ->when(
                    $idestacion,
                    function ($query) use ($idestacion) {

                        $query->where(
                            'id_gas',
                            $idestacion
                        );
                    }
                )
                ->orderByDesc('id')
                ->get()
                ->map(
                    function ($usuario) {

                        return [
                            'id' => $usuario->id,

                            'nombre' => $usuario->nombre,

                            'email' => $usuario->email,

                            'telefono' => $usuario->telefono,

                            'id_gas' => $usuario->id_gas,

                            'id_puesto' => $usuario->id_puesto,

                            'usuario' => $usuario->usuario,

                            'razonsocial' =>
                            $usuario->estacion?->razonsocial,

                            'puesto' =>
                            $usuario->puesto?->tipo_puesto,

                            'estatus' => $usuario->estatus
                        ];
                    }
                )
                ->values();

            JsonResponse::custom([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar los usuarios.',
                'data' => []
            ]);
        }
    }


    /**
     * Obtener usuario para edición.
     */
    public function obtenerUsuario(): void
    {
        if (!$this->tieneAccesoUsuarios()) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No autorizado.'
            ]);

            return;
        }

        $id = isset($_GET['id'])
            && is_numeric($_GET['id'])
            ? (int) $_GET['id']
            : 0;

        if ($id <= 0) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Usuario no válido.'
            ]);

            return;
        }

        $usuario = Usuario::select([
            'id',
            'nombre',
            'email',
            'telefono',
            'id_gas',
            'id_puesto',
            'usuario',
            'fecha_nacimiento',
            'estado_civil',
            'seguro_social',
            'domicilio',
            'fecha_ingreso',
            'responsabilidad_sgm',
            'estatus'
        ])
            ->find($id);

        if (!$usuario) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'El usuario no existe.'
            ]);

            return;
        }

        JsonResponse::custom([
            'success' => true,

            'data' => [
                'id' => $usuario->id,

                'nombre' => $usuario->nombre,

                'email' => $usuario->email,

                'telefono' => $usuario->telefono,

                'id_gas' => $usuario->id_gas,

                'id_puesto' => $usuario->id_puesto,

                'usuario' => $usuario->usuario,

                'fecha_nacimiento' =>
                $usuario->fecha_nacimiento,

                'estado_civil' =>
                $usuario->estado_civil,

                'seguro_social' =>
                $usuario->seguro_social,

                'domicilio' =>
                $usuario->domicilio,

                'fecha_ingreso' =>
                $usuario->fecha_ingreso,

                'responsabilidad_sgm' =>
                $usuario->responsabilidad_sgm,

                'estatus' =>
                $usuario->estatus
            ]
        ]);
    }


    /**
     * Crear usuario.
     */
    public function crearUsuario(): void
    {
        if (!$this->tieneAccesoUsuarios()) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No autorizado.'
            ]);

            return;
        }

        try {

            $data = $this->requestData();

            $error = $this->validarUsuario(
                $data,
                true
            );

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }

            /**
             * Validamos estación.
             */
            $estacion = Estacion::find(
                (int) $data['id_gas']
            );

            if (!$estacion) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La estación seleccionada no existe.'
                ]);

                return;
            }

            /**
             * Validamos puesto.
             */
            $puesto = Puestos::find(
                (int) $data['id_puesto']
            );

            if (!$puesto) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El puesto seleccionado no existe.'
                ]);

                return;
            }

            $usuario = new Usuario();

            $usuario->nombre =
                trim((string) $data['nombre']);

            $usuario->email =
                trim((string) $data['email']);

            $usuario->telefono =
                trim((string) ($data['telefono'] ?? ''));

            $usuario->id_gas =
                (int) $data['id_gas'];

            $usuario->id_puesto =
                (int) $data['id_puesto'];

            $usuario->usuario =
                trim((string) $data['usuario']);

            $usuario->password =
                $data['password'];

            $usuario->fecha_nacimiento =
                $data['fecha_nacimiento'] ?? '';

            $usuario->estado_civil =
                trim((string) ($data['estado_civil'] ?? ''));

            $usuario->seguro_social =
                trim((string) ($data['seguro_social'] ?? ''));

            $usuario->domicilio =
                trim((string) ($data['domicilio'] ?? ''));

            $usuario->fecha_ingreso =
                $data['fecha_ingreso'] ?? '';

            $usuario->responsabilidad_sgm =
                trim((string) ($data['responsabilidad_sgm'] ?? ''));

            /*
 * La firma todavía no se captura desde el formulario.
 * Se inicializa vacía para cumplir con la columna NOT NULL.
 */
            $usuario->firma = '';
            $usuario->bitacora_app = 0;

            $usuario->estatus = 0;

            $usuario->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Usuario creado correctamente.',
                'id' => $usuario->id
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',

                /**
                 * Durante desarrollo puedes usar:
                 *
                 * 'message' => $e->getMessage()
                 */
                'message' => $e->getMessage()
            ]);
        }
    }


    /**
     * Actualizar usuario.
     */
    public function actualizarUsuario(): void
    {
        if (!$this->tieneAccesoUsuarios()) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No autorizado.'
            ]);

            return;
        }

        try {

            $data = $this->requestData();

            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Usuario no válido.'
                ]);

                return;
            }

            $usuario = Usuario::find($id);

            if (!$usuario) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El usuario no existe.'
                ]);

                return;
            }

            $error = $this->validarUsuario(
                $data,
                false,
                $id
            );

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }

            /**
             * Validamos estación.
             */
            if (
                !Estacion::where(
                    'id',
                    (int) $data['id_gas']
                )->exists()
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La estación seleccionada no existe.'
                ]);

                return;
            }

            /**
             * Validamos puesto.
             */
            if (
                !Puestos::where(
                    'id',
                    (int) $data['id_puesto']
                )->exists()
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El puesto seleccionado no existe.'
                ]);

                return;
            }

            $usuario->nombre =
                trim(
                    (string) $data['nombre']
                );

            $usuario->email =
                trim(
                    (string) $data['email']
                );

            $usuario->telefono =
                trim(
                    (string) ($data['telefono'] ?? '')
                );

            $usuario->id_gas =
                (int) $data['id_gas'];

            $usuario->id_puesto =
                (int) $data['id_puesto'];

            $usuario->usuario =
                trim(
                    (string) $data['usuario']
                );

            $usuario->fecha_nacimiento =
                $data['fecha_nacimiento'] ?? '';

            $usuario->estado_civil =
                trim(
                    (string) ($data['estado_civil'] ?? '')
                );

            $usuario->seguro_social =
                trim(
                    (string) ($data['seguro_social'] ?? '')
                );

            $usuario->domicilio =
                trim(
                    (string) ($data['domicilio'] ?? '')
                );

            $usuario->fecha_ingreso =
                $data['fecha_ingreso'] ?? '';

            $usuario->responsabilidad_sgm =
                trim(
                    (string) ($data['responsabilidad_sgm'] ?? '')
                );

            /**
             * Solo actualizamos contraseña
             * si el usuario escribió una nueva.
             */
            if (
                trim(
                    (string) ($data['password'] ?? '')
                ) !== ''
            ) {

                $usuario->password =
                    password_hash(
                        (string) $data['password'],
                        PASSWORD_DEFAULT
                    );
            }

            $usuario->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Usuario actualizado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible actualizar el usuario.'
            ]);
        }
    }


    /**
     * Baja lógica.
     */
    public function eliminarUsuario(): void
    {
        if (!$this->tieneAccesoUsuarios()) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No autorizado.'
            ]);

            return;
        }

        try {

            $data = $this->requestData();

            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Usuario no válido.'
                ]);

                return;
            }

            $usuario = Usuario::find($id);

            if (!$usuario) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El usuario no existe.'
                ]);

                return;
            }

            if ((int) $usuario->estatus !== 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'El usuario ya se encuentra eliminado.'
                ]);

                return;
            }

            $usuario->estatus = 1;

            $usuario->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Usuario eliminado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible eliminar el usuario.'
            ]);
        }
    }


    /**
     * Validación de acceso.
     *
     * Solo id_puesto = 25.
     */
    private function tieneAccesoUsuarios(): bool
    {
        return Usuario::where(
            'id',
            $this->userId()
        )
            ->where(
                'id_puesto',
                25
            )
            ->exists();
    }


    /**
     * Obtener JSON enviado por Axios.
     */
    private function requestData(): array
    {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (is_array($input)) {
            return $input;
        }

        return $_POST ?? [];
    }


    /**
     * Validación del formulario.
     */
    private function validarUsuario(
        array $data,
        bool $crear,
        ?int $id = null
    ): ?string {

        $campos = [
            'nombre' => 'Nombre',

            'usuario' => 'Usuario',

            'id_gas' => 'Estación',

            'id_puesto' => 'Puesto',

            'fecha_nacimiento' => 'Fecha de nacimiento',

            'fecha_ingreso' => 'Fecha de ingreso'
        ];

        foreach ($campos as $campo => $nombre) {

            if (
                !isset($data[$campo])
                || trim(
                    (string) $data[$campo]
                ) === ''
            ) {

                return
                    "El campo {$nombre} es obligatorio.";
            }
        }

        /**
         * Contraseña obligatoria solo al crear.
         */
        if (
            $crear
            && trim(
                (string) ($data['password'] ?? '')
            ) === ''
        ) {

            return 'La contraseña es obligatoria.';
        }

        /**
         * Estación válida.
         */
        if (
            !is_numeric($data['id_gas'])
            || (int) $data['id_gas'] <= 0
        ) {

            return 'Selecciona una estación válida.';
        }

        /**
         * Puesto válido.
         */
        if (
            !is_numeric($data['id_puesto'])
            || (int) $data['id_puesto'] <= 0
        ) {

            return 'Selecciona un puesto válido.';
        }

        /**
         * Usuario/login duplicado.
         */
        $usuarioExiste = Usuario::where(
            'usuario',
            trim(
                (string) $data['usuario']
            )
        )
            ->when(
                $id,
                function ($query) use ($id) {

                    $query->where(
                        'id',
                        '!=',
                        $id
                    );
                }
            )
            ->exists();

        if ($usuarioExiste) {

            return
                'Ya existe un usuario con ese nombre de usuario.';
        }

        return null;
    }
}
