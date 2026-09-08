<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Estacion;
use App\Models\Usuario;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

class EstacionController extends BaseController
{

    public function viewIndex(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $title = 'Estaciones';

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
                '/js/estaciones/datatable.init.js?v=' . time(),
                '/js/estaciones/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'estaciones/index',
            $data,
            'main'
        );
    }

    public function datatableEstaciones(): void
    {
        try {

            $usuario = Usuario::with('puesto')
                ->where('id', $this->userId())
                ->first();

            $query = Estacion::query()
                ->orderBy('numlista');

            if (
                $usuario?->puesto
                && $usuario->puesto->tipo_puesto === 'Gestoria'
            ) {
                $query->whereNotIn('id', [8, 10, 13]);
            }

            $estaciones = $query->get();

            JsonResponse::custom([
                'success' => true,
                'data' => $estaciones
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error('No fue posible cargar las estaciones.');
        }
    }

    public function crearEstacion(): void
    {
        try {

            $data = $this->requestData();

            $error = $this->validarEstacion($data);

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }


            $estacion = DB::transaction(
                function () use ($data) {

                    $ultimoNumero = Estacion::max('numlista');

                    $payload = $this->crearPayload(
                        $data
                    );

                    $payload['numlista'] =
                        ((int) $ultimoNumero) + 1;

                    $payload['latitud'] = 0;
                    $payload['longitud'] = 0;
                    $payload['ubicacion'] = 0;
                    $payload['estatus'] = 1;

                    return Estacion::create(
                        $payload
                    );
                }
            );


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Estación creada correctamente.',
                'id' => $estacion->id
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => $e->getMessage()
                // En desarrollo:
                // 'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerEstacion(int $id): void
    {
        try {

            $estacion = Estacion::find($id);

            if (!$estacion) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'La estación no existe.'
                ]);

                return;
            }


            JsonResponse::custom([
                'success' => true,
                'data' => $estacion
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible obtener la estación.'
            ]);
        }
    }

    public function actualizarEstacion(): void
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
                    'message' => 'La estación no es válida.'
                ]);

                return;
            }


            $estacion = Estacion::find($id);

            if (!$estacion) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La estación no existe.'
                ]);

                return;
            }


            $error = $this->validarEstacion($data);

            if ($error !== null) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => $error
                ]);

                return;
            }


            $payload = $this->crearPayload(
                $data
            );


            $estacion->fill($payload);

            $estacion->save();


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Estación actualizada correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible actualizar la estación.'
            ]);
        }
    }

    public function eliminarEstacion(): void
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
                    'message' => 'La estación no es válida.'
                ]);

                return;
            }


            $estacion = Estacion::find($id);

            if (!$estacion) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La estación no existe.'
                ]);

                return;
            }


            if ((int) $estacion->estatus === 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'La estación ya se encuentra cancelada.'
                ]);

                return;
            }


            $estacion->estatus = 0;

            $estacion->save();


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Estación cancelada correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible cancelar la estación.'
            ]);
        }
    }

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

    private function validarEstacion(array $data): ?string
    {
        $campos = [
            'nombre' => 'Nombre de la estación',
            'permisocre' => 'Permiso CRE',
            'razonsocial' => 'Razón social',
            'rfc' => 'RFC',
            'direccioncompleta' => 'Dirección completa',
            'di_estado' => 'Estado',
            'di_municipio' => 'Municipio',
            'apoderado_legal' => 'Apoderado legal',
            'fecha_autorizacion' => 'Fecha de autorización',
            'distmax' => 'Distancia máxima',
        ];


        foreach ($campos as $campo => $nombre) {

            if (
                !isset($data[$campo])
                || trim((string) $data[$campo]) === ''
            ) {
                return "El campo {$nombre} es obligatorio.";
            }
        }


        if (!is_numeric($data['distmax'])) {
            return 'La distancia máxima debe ser un valor numérico.';
        }


        return null;
    }

    private function crearPayload(array $data): array
    {
        return [
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'es' => trim((string) ($data['es'] ?? '')),
            'permisocre' => trim((string) ($data['permisocre'] ?? '')),
            'razonsocial' => trim((string) ($data['razonsocial'] ?? '')),
            'rfc' => strtoupper(trim((string) ($data['rfc'] ?? ''))),
            'direccioncompleta' => trim((string) ($data['direccioncompleta'] ?? '')),
            'di_estado' => trim((string) ($data['di_estado'] ?? '')),
            'di_municipio' => trim((string) ($data['di_municipio'] ?? '')),
            'apoderado_legal' => trim((string) ($data['apoderado_legal'] ?? '')),
            'firma' => trim((string) ($data['firma'] ?? '')),
            'politica' => trim((string) ($data['politica'] ?? '')),
            'mision' => trim((string) ($data['mision'] ?? '')),
            'vision' => trim((string) ($data['vision'] ?? '')),
            'franquicia' => trim((string) ($data['franquicia'] ?? '')),
            'producto_uno' => trim((string) ($data['producto_uno'] ?? '')),
            'producto_dos' => trim((string) ($data['producto_dos'] ?? '')),
            'producto_tres' => trim((string) ($data['producto_tres'] ?? '')),
            'sasisopa' => trim((string) ($data['sasisopa'] ?? '')),
            'fecha_autorizacion' => $data['fecha_autorizacion'] ?? null,
            'organigrama' => trim((string) ($data['organigrama'] ?? '')),
            'volumetrico' => trim((string) ($data['volumetrico'] ?? '')),
            'noregistro_generador' => trim(
                (string) ($data['noregistro_generador'] ?? '')
            ),
            'categoria' => trim(
                (string) ($data['categoria'] ?? '')
            ),
            'distmax' => (float) ($data['distmax'] ?? 0),
        ];
    }
}
