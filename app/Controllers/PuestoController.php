<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Models\Puestos;

class PuestoController extends BaseController
{
    /**
     * Vista principal.
     */
    public function index(): void
    {
        $title = 'Puestos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,

            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',

                '/js/puestos/datatable.init.js?v=' . time(),
                '/js/puestos/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'puestos/index',
            $data,
            'main'
        );
    }


    /**
     * DataTable.
     */
    public function datatablePuestos(): void
    {
        try {

            $puestos = Puestos::select([
                'id',
                'tipo_puesto',
                'estatus'
            ])
                ->orderByDesc('id')
                ->get();

            JsonResponse::custom([
                'success' => true,
                'data' => $puestos
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar los puestos.',
                'data' => []
            ]);
        }
    }


    /**
     * Catálogo de puestos activos.
     *
     * Se conserva porque ya lo utiliza
     * tu aplicación.
     */
    public function getPuestos(): void
    {
        try {

            $puestos = Puestos::where('estatus', 0)
                ->select([
                    'id',
                    'tipo_puesto'
                ])
                ->orderBy('tipo_puesto')
                ->get();

            JsonResponse::custom($puestos);
        } catch (\Throwable $e) {

            JsonResponse::custom([]);
        }
    }


    /**
     * Obtener puesto para editar.
     */
    public function obtenerPuesto(): void
    {
        $id = isset($_GET['id'])
            && is_numeric($_GET['id'])
            ? (int) $_GET['id']
            : 0;

        if ($id <= 0) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Puesto no válido.'
            ]);

            return;
        }

        $puesto = Puestos::select([
            'id',
            'tipo_puesto',
            'estatus'
        ])
            ->find($id);

        if (!$puesto) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'El puesto no existe.'
            ]);

            return;
        }

        JsonResponse::custom([
            'success' => true,
            'data' => $puesto
        ]);
    }


    /**
     * Crear puesto.
     */
    public function crearPuesto(): void
    {
        try {

            $data = $this->requestData();

            $error = $this->validarPuesto(
                $data
            );

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }

            $nombre = trim(
                (string) $data['tipo_puesto']
            );

            /**
             * Evitar nombres duplicados.
             */
            $existe = Puestos::where(
                'tipo_puesto',
                $nombre
            )
                ->exists();

            if ($existe) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'Ya existe un puesto con ese nombre.'
                ]);

                return;
            }

            $puesto = new Puestos();

            $puesto->tipo_puesto = $nombre;

            /**
             * Según la lógica existente:
             *
             * 0 = Activo
             * 1 = Cancelado
             */
            $puesto->estatus = 0;

            $puesto->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Puesto creado correctamente.',
                'id' => $puesto->id
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible crear el puesto.'
            ]);
        }
    }


    /**
     * Actualizar puesto.
     */
    public function actualizarPuesto(): void
    {
        try {

            $data = $this->requestData();

            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Puesto no válido.'
                ]);

                return;
            }

            $puesto = Puestos::find($id);

            if (!$puesto) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El puesto no existe.'
                ]);

                return;
            }

            $error = $this->validarPuesto(
                $data
            );

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }

            $nombre = trim(
                (string) $data['tipo_puesto']
            );

            /**
             * Evitar nombre duplicado
             * excepto el registro actual.
             */
            $existe = Puestos::where(
                'tipo_puesto',
                $nombre
            )
                ->where(
                    'id',
                    '!=',
                    $id
                )
                ->exists();

            if ($existe) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'Ya existe otro puesto con ese nombre.'
                ]);

                return;
            }

            $puesto->tipo_puesto = $nombre;

            /**
             * No modificamos estatus al editar.
             */
            $puesto->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Puesto actualizado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible actualizar el puesto.'
            ]);
        }
    }


    /**
     * Cancelación lógica.
     */
    public function eliminarPuesto(): void
    {
        try {

            $data = $this->requestData();

            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Puesto no válido.'
                ]);

                return;
            }

            $puesto = Puestos::find($id);

            if (!$puesto) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El puesto no existe.'
                ]);

                return;
            }

            if ((int) $puesto->estatus === 1) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'El puesto ya se encuentra cancelado.'
                ]);

                return;
            }

            $puesto->estatus = 1;

            $puesto->save();

            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Puesto cancelado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible cancelar el puesto.'
            ]);
        }
    }


    /**
     * Validación.
     */
    private function validarPuesto(
        array $data
    ): ?string {

        if (
            !isset($data['tipo_puesto'])
            || trim(
                (string) $data['tipo_puesto']
            ) === ''
        ) {

            return 'El nombre del puesto es obligatorio.';
        }

        return null;
    }


    /**
     * Leer JSON enviado mediante Axios.
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
}
