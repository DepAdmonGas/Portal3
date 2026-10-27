<?php

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteDiaHist;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Services\ModuleStationService;

class SistemasCorteDiarioController extends BaseController
{
    public function index(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $estacionesAutorizadas = ModuleStationService::getAvailableStations('corte-diario');
        $contexto = ModuleStationService::getContext('corte-diario');
        $idEstacion = $contexto['id_estacion'] ?? null;
        $idsAutorizados = array_map('intval', array_column($estacionesAutorizadas, 'id'));

        if ($idEstacion !== null && !in_array((int) $idEstacion, $idsAutorizados, true)) {
            ModuleStationService::setContext('corte-diario', null, null);
            $idEstacion = null;
        }

        $title = 'Corte Diario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'idEstacion' => $idEstacion,
            'moduleStationKey' => 'corte-diario',
            'moduleStationPlaceholder' => 'Selecciona una estación',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
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
            if (!$this->usuarioPuedeAcceder()) {
                JsonResponse::custom([
                    'success' => false,
                    'message' => 'No autorizado.',
                    'data' => []
                ], 403);
            }

            $idEstacion = $this->estacionSeleccionadaAutorizada();

            if ($idEstacion === null) {

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

                            'fecha_formateada' => formatearFecha($corte->fecha),

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

            $idEstacion = $this->validarContextoOperacion();

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

            if (!$this->cortePerteneceAEstacion($id, $idEstacion)) {
                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El corte no pertenece a la estación seleccionada.'
                ], 403);
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

            $idEstacion = $this->validarContextoOperacion();

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

            if (!$this->cortePerteneceAEstacion($id, $idEstacion)) {
                JsonResponse::custom([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'El corte no pertenece a la estación seleccionada.'
                ], 403);
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


    private function usuarioPuedeAcceder(): bool
    {
        return Usuario::where('id', $this->userId())
            ->where('id_puesto', 25)
            ->exists();
    }


    private function estacionSeleccionadaAutorizada(): ?int
    {
        $idEstacion = ModuleStationService::getContext('corte-diario')['id_estacion'] ?? null;
        if ($idEstacion === null) {
            return null;
        }

        $idsAutorizados = array_map(
            'intval',
            array_column(ModuleStationService::getAvailableStations('corte-diario'), 'id')
        );

        return in_array((int) $idEstacion, $idsAutorizados, true)
            ? (int) $idEstacion
            : null;
    }


    private function validarContextoOperacion(): int
    {
        if (!$this->usuarioPuedeAcceder()) {
            JsonResponse::custom([
                'success' => false,
                'type' => 'error',
                'message' => 'No autorizado.'
            ], 403);
        }

        $idEstacion = $this->estacionSeleccionadaAutorizada();
        if ($idEstacion === null) {
            JsonResponse::custom([
                'success' => false,
                'type' => 'warning',
                'message' => 'Selecciona una estación autorizada.'
            ], 422);
        }

        return $idEstacion;
    }


    private function cortePerteneceAEstacion(int $idCorte, int $idEstacion): bool
    {
        return CorteDia::query()
            ->join('op_corte_mes', 'op_corte_dia.id_mes', '=', 'op_corte_mes.id')
            ->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
            ->where('op_corte_dia.id', $idCorte)
            ->where('op_corte_year.id_estacion', $idEstacion)
            ->exists();
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
