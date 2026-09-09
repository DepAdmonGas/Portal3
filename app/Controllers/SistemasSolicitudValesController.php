<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\SolicitudVale;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

class SistemasSolicitudValesController extends BaseController
{

    public function index(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $title = 'Solicitud de Vales';

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
                '/js/solicitudvales/datatable.init.js?v=' . time(),
                '/js/solicitudvales/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'solicitudvales/index',
            $data,
            'main'
        );
    }

    public function datatableVales(): void
    {
        try {

            $vales = SolicitudVale::query()
                ->select([
                    'op_solicitud_vale.id',
                    'op_solicitud_vale.folio',
                    'op_solicitud_vale.fecha',
                    'op_solicitud_vale.hora',
                    'op_solicitud_vale.monto',
                    'op_solicitud_vale.concepto',
                    'op_solicitud_vale.solicitante',
                    'op_solicitud_vale.autorizado_por',
                    'op_solicitud_vale.metodo_autorizacion',
                    'op_solicitud_vale.cuenta',
                    'tb_estaciones.razonsocial'
                ])
                ->join(
                    'tb_estaciones',
                    'op_solicitud_vale.id_estacion',
                    '=',
                    'tb_estaciones.id'
                )
                ->join(
                    'tb_usuarios',
                    'op_solicitud_vale.id_usuario',
                    '=',
                    'tb_usuarios.id'
                )
                ->orderByDesc(
                    'op_solicitud_vale.folio'
                )
                ->get();

            JsonResponse::custom([
                'success' => true,
                'data' => $vales
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar las solicitudes de vale.',
                'data' => []
            ]);
        }
    }


    public function actualizarCampo(): void
    {
        try {

            $data = $this->requestData();

            $folio = isset($data['folio'])
                ? (int) $data['folio']
                : 0;

            $campo = trim(
                (string) ($data['campo'] ?? '')
            );

            $valor = $data['valor'] ?? null;


            if ($folio <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Folio no válido.'
                ]);

                return;
            }

            $camposPermitidos = [
                'monto',
                'concepto',
                'cuenta'
            ];


            if (
                !in_array(
                    $campo,
                    $camposPermitidos,
                    true
                )
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El campo seleccionado no puede modificarse.'
                ]);

                return;
            }


            $vale = SolicitudVale::where(
                'folio',
                $folio
            )
                ->first();


            if (!$vale) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La solicitud de vale no existe.'
                ]);

                return;
            }

            if ($campo === 'monto') {

                $valor = $this->normalizarMonto(
                    $valor
                );


                if ($valor === null) {

                    JsonResponse::custom([
                        'success' => false,
                        'type' => 'error',
                        'message' => 'Ingresa un monto válido.'
                    ]);

                    return;
                }
            }

            if (
                in_array(
                    $campo,
                    [
                        'concepto',
                        'cuenta'
                    ],
                    true
                )
            ) {

                $valor = trim(
                    (string) $valor
                );
            }


            $vale->{$campo} = $valor;

            $vale->save();


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Información actualizada correctamente.',

                'data' => [
                    'folio' => $vale->folio,
                    'campo' => $campo,
                    'valor' => $vale->{$campo}
                ]
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible actualizar la solicitud.'
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

    private function normalizarMonto(
        mixed $valor
    ): ?float {

        $valor = trim(
            (string) $valor
        );


        $valor = str_replace(
            [
                '$',
                ',',
                ' '
            ],
            '',
            $valor
        );


        if (
            $valor === ''
            || !is_numeric($valor)
        ) {

            return null;
        }


        return (float) $valor;
    }
}
