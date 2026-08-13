<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Usuario;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\PlanAtencionHallazgo;
use App\Models\Sgm\PlanAuditoriaResponsable;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmPlanAtencionHallazgoController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index(int $id)
    {
        $title = 'Plan de atencion de Hallazgos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add(
            '10. Auditorias, Internas, externas y Atención de hallazgos',
            '/sgm/auditorias-internas-externas-atencion-hallazgos'
        );
        Breadcrumb::add($title, '');

        $this->validaAuditoria(
            $this->estacionId(),
            $id
        );

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [],
            'scripts' => [
                '/js/sgm/auditorias/planatencionhallazgo.actions.init.js?v=1.5.0',
            ],
            'help' => false,
        ];

        View::render(
            'sgm/auditorias/plan-atencion-hallazgo',
            $data,
            'sgm'
        );
    }

    private function validaAuditoria(int $idEstacion, int $idRegistro): void
    {
        $realizadoPor = Autorizado::query()
            ->where('estado', 1)
            ->whereHas('usuario', function ($query) use ($idEstacion) {
                $query->where('id_gas', $idEstacion);
            })
            ->value('id_usuario');

        $existe = PlanAtencionHallazgo::query()
            ->where('id_auditoria', $idRegistro)
            ->exists();

        if (!$existe) {

            PlanAtencionHallazgo::create([
                'id_auditoria' => $idRegistro,
                'fecha' => date('Y-m-d'),
                'sitio_area' => '',
                'responsable' => 0,
                'hallazgo' => '',
                'analisis_causa' => '',
                'acciones_hallazgos' => '',
                'fecha_complimiento' => '',
                'recursos_implementacion' => '',
                'fecha_atencion_hallazgos' => date('Y-m-d'),
                'responsable_sgm' => 0,
                'realizadopor' => $realizadoPor ?? 0,
            ]);
        }
    }

    public function data(int $id): void
    {
        $estacionId = $this->estacionId();

        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $estacionId)
            ->with('estacion')
            ->firstOrFail();

        $plan = PlanAtencionHallazgo::query()
            ->firstOrCreate(
                [
                    'id_auditoria' => $auditoria->id,
                ],
                [
                    'fecha' => null,
                    'sitio_area' => null,
                    'responsable' => 0,
                    'hallazgo' => null,
                    'analisis_causa' => null,
                    'acciones_hallazgos' => null,
                    'fecha_complimiento' => null,
                    'recursos_implementacion' => null,
                    'fecha_atencion_hallazgos' => null,
                    'responsable_sgm' => 0,
                    'realizadopor' => 0,
                ]
            );

        $usuarios = Usuario::query()
            ->where('id_gas', $estacionId)
            ->where('estatus', 0)
            ->with('puesto')
            ->orderBy('nombre')
            ->get()
            ->map(function ($usuario) {
                return [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'puesto' => $usuario->puesto?->tipo_puesto ?? '',
                    'firma' => $usuario->firma ?? '',
                ];
            })
            ->values();

        $responsable = null;

        if ((int) $plan->responsable > 0) {
            $responsable = $usuarios->firstWhere(
                'id',
                (int) $plan->responsable
            );
        }

        $responsableSgm = null;

        if ((int) $plan->responsable_sgm > 0) {
            $responsableSgm = $usuarios->firstWhere(
                'id',
                (int) $plan->responsable_sgm
            );
        }


        $responsables = PlanAuditoriaResponsable::query()
            ->where('id_plan', $plan->id)
            ->with('usuario.puesto')
            ->orderBy('id')
            ->get()
            ->map(function ($responsable) {
                return [
                    'id' => $responsable->id,

                    'id_plan' => $responsable->id_plan,

                    'id_responsable' =>
                    $responsable->id_responsable,

                    'nombre' =>
                    $responsable->usuario?->nombre ?? '',

                    'puesto' =>
                    $responsable->usuario?->puesto?->tipo_puesto ?? '',

                    'firma' =>
                    $responsable->usuario?->firma ?? '',
                ];
            })
            ->values();


        $realizadoPor = null;

        if ((int) $plan->realizadopor > 0) {

            $realizadoPor = $usuarios->firstWhere(
                'id',
                (int) $plan->realizadopor
            );
        }


        JsonResponse::custom([
            'success' => true,

            'data' => [

                'plan' => [

                    'id' =>
                    $plan->id,

                    'id_auditoria' =>
                    $plan->id_auditoria,

                    /*
                 * Datos generales
                 */
                    'razon_social' =>
                    $auditoria->estacion?->razonsocial,

                    'permiso_cre' =>
                    $auditoria->estacion?->permisocre,

                    'fecha' =>
                    $plan->fecha
                        ? \Carbon\Carbon::parse($plan->fecha)->format('Y-m-d')
                        : null,

                    'sitio_area' =>
                    $plan->sitio_area,

                    'responsable' =>
                    $plan->responsable,

                    'responsable_data' =>
                    $responsable,

                    /*
                 * Hallazgo
                 */
                    'hallazgo' =>
                    $plan->hallazgo,

                    /*
                 * Análisis de causa
                 */
                    'analisis_causa' =>
                    $plan->analisis_causa,

                    /*
                 * Acciones
                 */
                    'acciones_hallazgos' =>
                    $plan->acciones_hallazgos,

                    /*
                 * Fechas / recursos
                 */
                    'fecha_complimiento' =>
                    $plan->fecha_complimiento,

                    'recursos_implementacion' =>
                    $plan->recursos_implementacion,

                    /*
                 * Segunda parte del plan
                 */
                    'fecha_atencion_hallazgos' =>
                    $plan->fecha_atencion_hallazgos->format('Y-m-d'),

                    'responsable_sgm' =>
                    $plan->responsable_sgm,

                    'responsable_sgm_data' =>
                    $responsableSgm,

                    /*
                 * Usuario que creó/realizó
                 */
                    'realizadopor' =>
                    $plan->realizadopor,

                    'realizadopor_data' =>
                    $realizadoPor,
                ],

                'responsables' =>
                $responsables,

                'usuarios' =>
                $usuarios,
            ],
        ]);
    }

    public function createResponsable(): void
    {
        $id = (int) Request::jsonInput('id');
        $idResponsable = (int) Request::jsonInput('id_responsable');

        /*
     * El "id" recibido por Alpine corresponde al
     * id de la auditoría.
     */
        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        /*
     * Obtener / crear el Plan de Atención de Hallazgos.
     */
        $plan = PlanAtencionHallazgo::query()
            ->where('id_auditoria', $auditoria->id)
            ->first();

        if (!$plan) {

            $plan = PlanAtencionHallazgo::create([
                'id_auditoria' => $auditoria->id,
                'fecha' => null,
                'sitio_area' => null,
                'responsable' => 0,
                'hallazgo' => null,
                'analisis_causa' => null,
                'acciones_hallazgos' => null,
                'fecha_complimiento' => null,
                'recursos_implementacion' => null,
                'fecha_atencion_hallazgos' => null,
                'responsable_sgm' => 0,
                'realizadopor' => 0,
            ]);
        }

        /*
     * Validar que el usuario pertenezca
     * a la estación actual.
     */
        $usuario = Usuario::query()
            ->where('id', $idResponsable)
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->with('puesto')
            ->firstOrFail();

        /*
     * Evitar responsables duplicados.
     */
        $existe = PlanAuditoriaResponsable::query()
            ->where('id_plan', $plan->id)
            ->where('id_responsable', $usuario->id)
            ->exists();

        if ($existe) {

            JsonResponse::error(
                'El usuario ya está asignado al plan.'
            );

            return;
        }

        $responsable = PlanAuditoriaResponsable::create([
            'id_plan' => $plan->id,
            'id_responsable' => $usuario->id,
        ]);

        JsonResponse::success(
            'Responsable creado',
            [
                'data' => [
                    'id' =>
                    $responsable->id,

                    'id_plan' =>
                    $responsable->id_plan,

                    'id_responsable' =>
                    $responsable->id_responsable,

                    'nombre' =>
                    $usuario->nombre,

                    'puesto' =>
                    $usuario->puesto?->tipo_puesto ?? '',

                    'firma' =>
                    $usuario->firma ?? '',
                ],
            ]
        );
    }

    public function deleteResponsable()
    {
        $id = Request::jsonInput('id');

        $responsable = PlanAuditoriaResponsable::query()
            ->where('id', $id)
            ->firstOrFail();

        $responsable->delete();

        JsonResponse::success(
            'Responsable eliminado correctamente.'
        );
    }

    public function editar(): void
    {
        $id = (int) Request::input('id');
        $campo = Request::input('campo');
        $valor = Request::input('valor');

        $camposPermitidos = [
            'fecha',
            'sitio_area',
            'responsable',
            'hallazgo',
            'analisis_causa',
            'acciones_hallazgos',
            'fecha_complimiento',
            'recursos_implementacion',
            'fecha_atencion_hallazgos',
            'responsable_sgm',
        ];

        if (!in_array($campo, $camposPermitidos, true)) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Campo no permitido.',
            ]);

            return;
        }

        $plan = PlanAtencionHallazgo::query()
            ->where('id', $id)
            ->whereHas('auditoria', function ($query) {
                $query->where(
                    'id_estacion',
                    $this->estacionId()
                );
            })
            ->firstOrFail();

        if (
            in_array($campo, [
                'responsable',
                'responsable_sgm',
            ], true)
            && (int) $valor > 0
        ) {

            Usuario::query()
                ->where('id', (int) $valor)
                ->where('id_gas', $this->estacionId())
                ->where('estatus', 0)
                ->firstOrFail();
        }

        $plan->{$campo} = $valor;
        $plan->save();

        JsonResponse::custom([
            'success' => true,
            'message' => 'Información actualizada correctamente.',
        ]);
    }

    public function pdf(int $id): void
    {
        $plan = PlanAtencionHallazgo::query()->where('id_auditoria', $id)->with(['auditoria.estacion',])->firstOrFail(); /* |-------------------------------------------------------------------------- | RESPONSABLE |-------------------------------------------------------------------------- */
        $responsable = null;
        if ($plan->responsable) {
            $responsable = Usuario::query()->where('id', $plan->responsable)->with('puesto')->first();
        } /* |-------------------------------------------------------------------------- | RESPONSABLE DEL SGM |-------------------------------------------------------------------------- */
        $responsableSgm = null;
        if ($plan->responsable_sgm) {
            $responsableSgm = Usuario::query()->where('id', $plan->responsable_sgm)->with('puesto')->first();
        } /* |-------------------------------------------------------------------------- | REALIZADO POR |-------------------------------------------------------------------------- */
        $realizadoPor = null;
        if ($plan->realizadopor) {
            $realizadoPor = Usuario::query()->where('id', $plan->realizadopor)->with('puesto')->first();
        } /* |-------------------------------------------------------------------------- | RESPONSABLES DEL CUMPLIMIENTO |-------------------------------------------------------------------------- */
        $responsables = PlanAuditoriaResponsable::query()->where('id_plan', $plan->id)->with('usuario')->orderBy('id')->get(); /* |-------------------------------------------------------------------------- | AUDITORÍA |-------------------------------------------------------------------------- */
        $auditoria = $plan->auditoria; /* |-------------------------------------------------------------------------- | CSS |-------------------------------------------------------------------------- */
        $css = file_get_contents('assets/css/pdf.css'); /* |-------------------------------------------------------------------------- | FIRMA |-------------------------------------------------------------------------- */
        $baseFirma = null;

        $rutaFirma = '';
        if ($responsableSgm?->firma) {
            $rutaFirma = ROOT_PATH . '/public/uploads/firma-personal/' . $responsableSgm->firma;
            if (is_file($rutaFirma)) {
                $dataFirma = file_get_contents($rutaFirma);
                if ($dataFirma !== false) {
                    $baseFirma = 'data:image/png;base64,' . base64_encode($dataFirma);
                }
            }
        }

        $fecha = '';
        if ($plan->fecha) {
            $fecha = formatearFecha($plan->fecha instanceof \Carbon\Carbon ? $plan->fecha->format('Y-m-d') : $plan->fecha);
        } /* |-------------------------------------------------------------------------- | FECHA DE ATENCIÓN |-------------------------------------------------------------------------- */
        $fechaAtencion = '';
        if ($plan->fecha_atencion_hallazgos) {
            $fechaAtencion = formatearFecha($plan->fecha_atencion_hallazgos instanceof \Carbon\Carbon ? $plan->fecha_atencion_hallazgos->format('Y-m-d') : $plan->fecha_atencion_hallazgos);
        } /* |-------------------------------------------------------------------------- | HTML |-------------------------------------------------------------------------- */
        $html = '';
        $html .= '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Plan de atención de Hallazgos</title>';
        $html .= ' <style> ' . $css . ' @page { margin: 0.5cm 0.5cm; font-family: Arial, Helvetica, sans-serif; } body { font-family: Arial, Helvetica, sans-serif; font-size: .8rem; color: #212529; margin: 0; } </style> ';
        $html .= '</head>';
        $html .= '<body>'; /* |-------------------------------------------------------------------------- | ENCABEZADO |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr> <td width="33%" align="center" rowspan="2" > ' . htmlspecialchars($auditoria?->estacion?->razonsocial ?? '', ENT_QUOTES, 'UTF-8') . ' </td> <td width="34%" align="center" rowspan="2" > <b> Plan de atención de Hallazgos </b> </td> <td width="33%" align="center" > <b> Fecha de autorización: 01-01-2024 </b> </td> </tr> <tr> <td align="center"> Fo.SGM.020 </td> </tr> <tr> <td align="center"> Realizado por:<br> ' . htmlspecialchars($realizadoPor?->nombre ?? '', ENT_QUOTES, 'UTF-8') . ' </td> <td align="center"> Revisado por:<br> Eduardo Galicia Flores </td> <td align="center"> Autorizado por:<br> ' . htmlspecialchars($auditoria?->estacion?->apoderado_legal ?? '', ENT_QUOTES, 'UTF-8') . ' </td> </tr> </tbody> </table> <br> '; /* |-------------------------------------------------------------------------- | I. DATOS GENERALES DEL PERMISIONARIO |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr> <td colspan="3" class="bg-secondary text-white" > <b> I. DATOS GENERALES DEL PERMISIONARIO </b> </td> </tr> <tr> <td class="bg-light"> NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL: </td> <td class="bg-light"> PERMISO CRE: </td> <td class="bg-light"> FECHA DEL INFORME DE AUDITORÍA (Reporte de hallazgos de auditorías): </td> </tr> <tr> <td class="bg-light"> ' . htmlspecialchars($auditoria?->estacion?->razonsocial ?? '', ENT_QUOTES, 'UTF-8') . ' </td> <td class="bg-light"> ' . htmlspecialchars($auditoria?->estacion?->permisocre ?? '', ENT_QUOTES, 'UTF-8') . ' </td> <td> ' . $fecha . ' </td> </tr> <tr> <td colspan="2" class="bg-light" > SITIO/ÁREA: </td> <td class="bg-light"> RESPONSABLE </td> </tr> <tr> <td colspan="2"> ' . htmlspecialchars($plan->sitio_area ?? '', ENT_QUOTES, 'UTF-8') . ' </td> <td> ' . htmlspecialchars($responsable?->nombre ?? '', ENT_QUOTES, 'UTF-8') . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | II. HALLAZGO |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr class="bg-secondary text-white" > <td colspan="3"> <b> II. HALLAZGO: (DESCRIPCIÓN/EVIDENCIA/CRITERIO) </b> </td> </tr> <tr> <td colspan="3"> ' . nl2br(htmlspecialchars($plan->hallazgo ?? '', ENT_QUOTES, 'UTF-8')) . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | III. ANÁLISIS DE LA CAUSA RAÍZ |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr class="bg-secondary text-white" > <td colspan="3"> <b> III. ANÁLISIS DE LA CAUSA RAÍZ </b> </td> </tr> <tr> <td colspan="3"> ' . nl2br(htmlspecialchars($plan->analisis_causa ?? '', ENT_QUOTES, 'UTF-8')) . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | IV. ACCIONES |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr class="bg-secondary text-white" > <td colspan="3"> <b> IV. ACCIONES PARA LA ATENCIÓN DE LOS HALLAZGOS NO CONFORMES </b> </td> </tr> <tr> <td colspan="3"> ' . nl2br(htmlspecialchars($plan->acciones_hallazgos ?? '', ENT_QUOTES, 'UTF-8')) . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | V. RESPONSABLES DEL CUMPLIMIENTO |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> 
        <tr> <td colspan="2" class="bg-secondary text-white align-middle" > <b> V. NOMBRE DE LOS RESPONSABLES DEL CUMPLIMIENTO DE LAS ACCIONES </b> </td> 
        <td class="p-0 m-0"> 
        
        <table class="table table-bordered table-sm m-0 p-0" > ';
        foreach ($responsables as $item) {
            $html .= ' <tr> <td> <small> ' . htmlspecialchars($item->usuario?->nombre ?? '', ENT_QUOTES, 'UTF-8') . ' </small> </td> </tr> ';
        }
        if ($responsables->count() === 0) {
            $html .= ' <tr> <td align="center"> No se encontró información para mostrar </td> </tr> ';
        }
        $html .= ' </table> </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | VI. FECHAS COMPROMISO |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr class="bg-secondary text-white" > <td colspan="3"> <b> VI. FECHAS COMPROMISO PARA EL CUMPLIMIENTO DE LA IMPLEMENTACIÓN DE ACCIONES </b> </td> </tr> <tr> <td colspan="3"> ' . nl2br(htmlspecialchars($plan->fecha_complimiento ?? '', ENT_QUOTES, 'UTF-8')) . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | VII. RECURSOS |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr class="bg-secondary text-white" > <td colspan="3"> <b> VII. RECURSOS ASIGNADOS PARA LA IMPLEMENTACIÓN DE ACCIONES </b> </td> </tr> <tr> <td colspan="3"> ' . nl2br(htmlspecialchars($plan->recursos_implementacion ?? '', ENT_QUOTES, 'UTF-8')) . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | FIRMA DEL RESPONSABLE DEL SGM |-------------------------------------------------------------------------- */
        $html .= ' <table class="table table-sm table-bordered"> <tbody> <tr> <td class="bg-light"> FECHA DEL PLAN DE ATENCIÓN DE HALLAZGOS: </td> <td> ' . $fechaAtencion . ' </td> </tr> <tr> <td class="bg-light align-middle" > RESPONSABLE DEL SGM: </td> <td class="text-center align-middle" > ';
        if ($baseFirma) {
            $html .= ' <div> <img src="' . $baseFirma . '" style="width: 100px;" > </div> ';
        }
        $html .= ' ' . htmlspecialchars($responsableSgm?->nombre ?? '', ENT_QUOTES, 'UTF-8') . ' </td> </tr> </tbody> </table> '; /* |-------------------------------------------------------------------------- | CIERRE HTML |-------------------------------------------------------------------------- */
        $html .= '</body>';
        $html .= '</html>'; /* |-------------------------------------------------------------------------- | DOMPDF |-------------------------------------------------------------------------- */
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Plan de atencion de Hallazgos.pdf', ['Attachment' => true,]);
    }
}
