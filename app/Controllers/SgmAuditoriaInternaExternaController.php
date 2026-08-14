<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Sgm\Auditoria;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmAuditoriaInternaExternaController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index()
    {
        $this->validarAuditoria();

        $title = '10. Auditorias, Internas, externas y Atención de hallazgos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sgm/auditorias/index.actions.init.js?v=1.3.0',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0.1',
                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0.1',
            ],
            'help' => true,
        ];

        View::render('sgm/auditorias/index', $data, 'sgm');
    }

    private function validarAuditoria(): void
    {
        Auditoria::query()->firstOrCreate(

            [
                'id_estacion' => $this->estacionId(),
                'year' => date('Y'),
            ],

            [
                'estado' => 0,
            ]
        );
    }


    public function table(): void
    {
        $auditorias = Auditoria::query()

            ->where(
                'id_estacion',
                $this->estacionId()
            )

            ->withCount([
                'planAuditoria',
                'hallazgos',
                'planAtencionHallazgos',
            ])

            ->orderByDesc('year')

            ->get()

            ->map(function ($item, $index) {

                return [

                    'id' => $item->id,
                    'numero' => $index + 1,
                    'year' => $item->year,
                    'plan18' => [
                        'pdf' => $item->plan_auditoria_count > 0,
                    ],
                    'hallazgo19' => [
                        'pdf' => $item->hallazgos_count > 0,
                    ],
                    'plan20' => [
                        'pdf' => $item->plan_atencion_hallazgos_count > 0,
                    ],
                ];
            });

        JsonResponse::custom([
            'success' => true,
            'data' => $auditorias,
        ]);
    }
}
