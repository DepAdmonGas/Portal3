<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteDiaHist;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

class SistemasCorteDiarioController extends BaseController
{
    public function index(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $estaciones = Estacion::select([
            'id',
            'nombre',
            'razonsocial'
        ])
            ->where('estatus', 1)
            ->orderBy('nombre')
            ->get();

        $title = 'Corte Diario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'estaciones' => $estaciones,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/cortediario/datatable.init.js?v=' . time(),
                '/js/cortediario/index.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'cortediario/index',
            $data,
            'main'
        );
    }

    public function datatable(): void
    {
        try {

            $idEstacion = isset($_GET['idEstacion'])
                && is_numeric($_GET['idEstacion'])
                ? (int) $_GET['idEstacion']
                : 0;


            if ($idEstacion <= 0) {

                JsonResponse::custom([
                    'success' => true,
                    'data' => []
                ]);

                return;
            }

            $cortes = CorteDia::query()
                ->select([
                    'op_corte_dia.id',
                    'op_corte_dia.id_mes',
                    'op_corte_dia.fecha',
                    'op_corte_dia.ventas',
                    'op_corte_dia.tpv',
                    'op_corte_dia.monedero',

                    'op_corte_mes.mes',

                    'op_corte_year.id AS id_corte_year',
                    'op_corte_year.year',

                    'tb_estaciones.id AS id_estacion',
                    'tb_estaciones.nombre AS estacion'
                ])
                ->join(
                    'op_corte_mes',
                    'op_corte_dia.id_mes',
                    '=',
                    'op_corte_mes.id'
                )
                ->join(
                    'op_corte_year',
                    'op_corte_mes.id_year',
                    '=',
                    'op_corte_year.id'
                )
                ->join(
                    'tb_estaciones',
                    'op_corte_year.id_estacion',
                    '=',
                    'tb_estaciones.id'
                )
                ->where(
                    'tb_estaciones.id',
                    $idEstacion
                )
                ->orderByDesc(
                    'op_corte_dia.fecha'
                )
                ->get()
                ->map(
                    function ($corte) {

                        $finalizado =
                            (int) $corte->ventas === 1
                            && (int) $corte->tpv === 1
                            && (int) $corte->monedero === 1;


                        return [
                            'id' => (int) $corte->id,

                            'id_mes' => (int) $corte->id_mes,

                            'fecha' => $corte->fecha,

                            'ventas' => (int) $corte->ventas,

                            'tpv' => (int) $corte->tpv,

                            'monedero' => (int) $corte->monedero,

                            'mes' => $corte->mes,

                            'year' => $corte->year,

                            'id_estacion' => (int) $corte->id_estacion,

                            'estacion' => $corte->estacion,

                            'finalizado' => $finalizado
                        ];
                    }
                )
                ->values();


            JsonResponse::custom([
                'success' => true,
                'data' => $cortes
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar los cortes diarios.',
                'data' => []
            ]);
        }
    }


    /**
     * =========================================================
     * ACTIVAR CORTE
     * =========================================================
     *
     * NO pide comentario.
     *
     * ventas   = 0
     * tpv      = 0
     * monedero = 0
     */
    public function activar(): void
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
                    'message' => 'Corte no válido.'
                ]);

                return;
            }


            $corte = CorteDia::find(
                $id
            );


            if (!$corte) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El corte diario no existe.'
                ]);

                return;
            }


            /*
             * Si los tres están en 1,
             * actualmente está finalizado.
             */
            $finalizado =
                (int) $corte->ventas === 1
                && (int) $corte->tpv === 1
                && (int) $corte->monedero === 1;


            if (!$finalizado) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'El corte ya se encuentra activo.'
                ]);

                return;
            }


            $usuarioId = (int) $this->userId();


            /*
             * Conservamos el historial legacy,
             * pero ya no pedimos comentario al usuario.
             */
            $corte
                ->getConnection()
                ->transaction(
                    function () use (
                        $corte,
                        $usuarioId
                    ) {

                        CorteDiaHist::create([
                            'id_corte' => $corte->id,
                            'id_usuario' => $usuarioId,
                            'detalle' => 'Activación manual'
                        ]);


                        $corte->ventas = 0;
                        $corte->tpv = 0;
                        $corte->monedero = 0;

                        $corte->save();
                    }
                );


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Corte activado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible activar el corte.'
            ]);
        }
    }


    /**
     * =========================================================
     * FINALIZAR CORTE
     * =========================================================
     *
     * ventas   = 1
     * tpv      = 1
     * monedero = 1
     */
    public function finalizar(): void
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
                    'message' => 'Corte no válido.'
                ]);

                return;
            }


            $corte = CorteDia::find(
                $id
            );


            if (!$corte) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El corte diario no existe.'
                ]);

                return;
            }


            $finalizado =
                (int) $corte->ventas === 1
                && (int) $corte->tpv === 1
                && (int) $corte->monedero === 1;


            if ($finalizado) {

                JsonResponse::custom([
                    'success' => false,
                    'type' => 'warning',
                    'message' => 'El corte ya se encuentra finalizado.'
                ]);

                return;
            }


            $corte->ventas = 1;
            $corte->tpv = 1;
            $corte->monedero = 1;

            $corte->save();


            JsonResponse::custom([
                'success' => true,
                'type' => 'success',
                'message' => 'Corte finalizado correctamente.'
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No fue posible finalizar el corte.'
            ]);
        }
    }


    /**
     * =========================================================
     * REQUEST JSON / POST
     * =========================================================
     */
    private function requestData(): array
    {
        $input = json_decode(
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
}
