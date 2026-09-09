<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\SolicitudCheque;
use App\Models\Operativo\SolicitudChequeFirma;
use App\Models\Operativo\SolicitudChequeToken;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

class SistemasSolicitudChequeController extends BaseController
{
    public function index(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $title = 'Solicitud de Cheques';

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
                '/js/solicitudcheque/datatable.init.js?v=' . time(),
                '/js/solicitudcheque/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'solicitudcheque/index',
            $data,
            'main'
        );
    }

    public function datatableCheques(): void
    {
        try {

            $inicio = date(
                'Y-m-01',
                strtotime('first day of last month')
            );

            $fin = date(
                'Y-m-01',
                strtotime('first day of next month')
            );


            $cheques = SolicitudCheque::query()
                ->select([
                    'op_solicitud_cheque.id',
                    'op_solicitud_cheque.id_estacion',
                    'op_solicitud_cheque.status',
                    'op_solicitud_cheque.fecha',
                    'op_solicitud_cheque.hora',
                    'op_solicitud_cheque.beneficiario',
                    'op_solicitud_cheque.monto',
                    'op_solicitud_cheque.no_factura',
                    'op_solicitud_cheque.razonsocial AS razon_social_solicitud',
                    'op_solicitud_cheque.concepto',
                    'op_solicitud_cheque.solicitante',
                    'tb_estaciones.razonsocial AS razon_social_estacion'
                ])
                ->join(
                    'tb_estaciones',
                    'op_solicitud_cheque.id_estacion',
                    '=',
                    'tb_estaciones.id'
                )
                ->where(
                    'op_solicitud_cheque.fecha',
                    '>=',
                    $inicio
                )
                ->where(
                    'op_solicitud_cheque.fecha',
                    '<',
                    $fin
                )
                ->where(
                    'op_solicitud_cheque.status',
                    1
                )
                ->orderByDesc(
                    'op_solicitud_cheque.id'
                )
                ->get();


            /*
             * Cargar cantidad de firmas en una sola consulta.
             */
            $ids = $cheques
                ->pluck('id')
                ->values();


            $firmasPorSolicitud = collect();


            if ($ids->isNotEmpty()) {

                $firmasPorSolicitud =
                    SolicitudChequeFirma::query()
                    ->whereIn(
                        'id_solicitud',
                        $ids
                    )
                    ->selectRaw(
                        'id_solicitud, COUNT(*) AS total'
                    )
                    ->groupBy(
                        'id_solicitud'
                    )
                    ->get()
                    ->keyBy(
                        'id_solicitud'
                    );
            }


            $data = $cheques
                ->map(
                    function ($cheque) use ($firmasPorSolicitud) {

                        /*
                         * Regla existente:
                         *
                         * estación 8:
                         * usa razón social guardada
                         * en la solicitud.
                         *
                         * resto:
                         * usa tb_estaciones.
                         */
                        $razonSocial =
                            (int) $cheque->id_estacion === 8

                            ? $cheque->razon_social_solicitud

                            : $cheque->razon_social_estacion;


                        $registroFirma =
                            $firmasPorSolicitud->get(
                                $cheque->id
                            );


                        $totalFirmas =
                            $registroFirma
                            ? (int) $registroFirma->total
                            : 0;


                        return [
                            'id' =>
                            (int) $cheque->id,

                            'id_estacion' =>
                            (int) $cheque->id_estacion,

                            'status' =>
                            (int) $cheque->status,

                            'fecha' =>
                            $cheque->fecha,

                            'hora' =>
                            $cheque->hora,

                            'beneficiario' =>
                            $cheque->beneficiario,

                            'monto' =>
                            $cheque->monto,

                            'no_factura' =>
                            $cheque->no_factura,

                            'concepto' =>
                            $cheque->concepto,

                            'solicitante' =>
                            $cheque->solicitante,

                            'razonsocial' =>
                            $razonSocial,

                            'firmas' =>
                            $totalFirmas
                        ];
                    }
                )
                ->values();


            JsonResponse::custom([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar las solicitudes de cheque.',
                'data' => []
            ]);
        }
    }


    /**
     * Edición inline.
     *
     * Únicamente:
     *
     * beneficiario
     * monto
     * no_factura
     * concepto
     */
    public function actualizarCampo(): void
    {
        try {

            $data = $this->requestData();

            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;

            $campo = trim(
                (string) ($data['campo'] ?? '')
            );

            $valor = $data['valor'] ?? null;


            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Solicitud no válida.'
                ]);

                return;
            }


            /*
             * Seguridad:
             * jamás aceptar una columna arbitraria
             * enviada desde JavaScript.
             */
            $camposPermitidos = [
                'beneficiario',
                'monto',
                'no_factura',
                'concepto'
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


            $cheque =
                SolicitudCheque::find(
                    $id
                );


            if (!$cheque) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La solicitud de cheque no existe.'
                ]);

                return;
            }


            /*
             * Monto.
             */
            if ($campo === 'monto') {

                $valor =
                    $this->normalizarMonto(
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


            /*
             * Campos texto.
             */
            if (
                in_array(
                    $campo,
                    [
                        'beneficiario',
                        'no_factura',
                        'concepto'
                    ],
                    true
                )
            ) {

                $valor =
                    trim(
                        (string) $valor
                    );
            }


            $cheque->{$campo} =
                $valor;


            $cheque->save();


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Información actualizada correctamente.',

                'data' => [
                    'id' =>
                    (int) $cheque->id,

                    'campo' =>
                    $campo,

                    'valor' =>
                    $cheque->{$campo}
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


    /**
     * Firma de solicitud.
     *
     * Conserva exactamente:
     *
     * opción 1:
     * usuario 19
     * tipo B
     * status 1
     *
     * opción 2:
     * usuario 2
     * tipo C
     * status 2
     */
    public function firmarCheque(): void
    {
        try {

            $data =
                $this->requestData();


            $id = isset($data['id'])
                ? (int) $data['id']
                : 0;


            $opcion = isset($data['opcion'])
                ? (int) $data['opcion']
                : 0;


            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Solicitud no válida.'
                ]);

                return;
            }


            if (
                !in_array(
                    $opcion,
                    [
                        1,
                        2
                    ],
                    true
                )
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'La solicitud ya cuenta con sus firmas correspondientes.'
                ]);

                return;
            }


            $cheque =
                SolicitudCheque::find(
                    $id
                );


            if (!$cheque) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'La solicitud de cheque no existe.'
                ]);

                return;
            }


            /*
             * Determinar estado actual de firmas.
             */
            $totalFirmas =
                SolicitudChequeFirma::where(
                    'id_solicitud',
                    $id
                )
                ->count();


            /*
             * Conservamos la secuencia del frontend legacy:
             *
             * 1 firma  -> opción 1
             * 2 firmas -> opción 2
             *
             * 3 firmas -> finalizada
             */
            if (
                $totalFirmas === 1
                && $opcion !== 1
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'La secuencia de firmas no es válida.'
                ]);

                return;
            }


            if (
                $totalFirmas === 2
                && $opcion !== 2
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'La secuencia de firmas no es válida.'
                ]);

                return;
            }


            if (
                $totalFirmas >= 3
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'La solicitud ya cuenta con sus firmas correspondientes.'
                ]);

                return;
            }


            /*
             * Regla legacy.
             */
            $usuario =
                $opcion === 2
                ? 2
                : 19;


            $tipoFirma =
                $opcion === 2
                ? 'C'
                : 'B';


            $status =
                $opcion;


            /*
             * Se conserva la generación de firma
             * que ya utilizaba el sistema.
             */
            $firma =
                'Firma: '
                . bin2hex(
                    random_bytes(64)
                )
                . '.'
                . uniqid();


            /*
             * Transacción Eloquent.
             */
            $cheque
                ->getConnection()
                ->transaction(
                    function () use (
                        $cheque,
                        $id,
                        $usuario,
                        $tipoFirma,
                        $firma,
                        $status
                    ) {

                        SolicitudChequeToken::create([
                            'id_solicitud' =>
                            $id,

                            'id_usuario' =>
                            $usuario,

                            'token' =>
                            $id
                        ]);


                        $cheque->status =
                            $status;

                        $cheque->save();


                        SolicitudChequeFirma::create([
                            'id_solicitud' =>
                            $id,

                            'id_usuario' =>
                            $usuario,

                            'tipo_firma' =>
                            $tipoFirma,

                            'firma' =>
                            $firma
                        ]);
                    }
                );


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Solicitud firmada correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible firmar la solicitud.'
            ]);
        }
    }


    /**
     * Leer JSON Axios.
     */
    private function requestData(): array
    {
        $input =
            json_decode(
                file_get_contents(
                    'php://input'
                ),
                true
            );


        if (is_array($input)) {
            return $input;
        }


        return $_POST ?? [];
    }


    /**
     * Normalizar monto.
     */
    private function normalizarMonto(
        mixed $valor
    ): ?float {

        $valor =
            trim(
                (string) $valor
            );


        $valor =
            str_replace(
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
