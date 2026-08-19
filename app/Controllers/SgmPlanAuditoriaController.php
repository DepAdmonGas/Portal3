<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Usuario;
use App\Models\Sgm\Elemento;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\PlanAuditoria;
use App\Models\Sgm\PlanAuditoriaAuditor;
use App\Models\Sgm\PlanAuditoriaAgenda;
use App\Models\Sgm\PlanAuditoriaResponsable;


use Dompdf\Dompdf;
use Dompdf\Options;

class SgmPlanAuditoriaController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index(int $id)
    {
        $title = 'Plan de Auditoria';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add(
            '10. Auditorias, Internas, externas y Atención de hallazgos',
            '/sgm/auditorias-internas-externas-atencion-hallazgos'
        );
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->with('planAuditoria')
            ->firstOrFail();

        $this->asegurarPlanAuditoria($auditoria);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [],
            'scripts' => [
                '/js/sgm/auditorias/planauditoria.actions.init.js?v=1.9.1',
            ],
            'help' => false,
        ];

        View::render(
            'sgm/auditorias/plan-auditoria',
            $data,
            'sgm'
        );
    }

    private function asegurarPlanAuditoria(
        Auditoria $auditoria
    ): PlanAuditoria {

        if ($auditoria->planAuditoria) {
            return $auditoria->planAuditoria;
        }

        $realizadoPor = Autorizado::query()
            ->where('estado', 1)
            ->whereHas('usuario', function ($query) {
                $query->where(
                    'id_gas',
                    $this->estacionId()
                );
            })
            ->value('id_usuario');

        return $auditoria->planAuditoria()->create([
            'fecha' => date('Y-m-d'),
            'nom_director' => '',
            'ubicacion_instalacion' => '',
            'objetivo_auditoria' => '',
            'alcance_auditoria' => '',
            'fecha_programada' => date('Y-m-d'),
            'sitio' => '',
            'metodo_auditoria' => '',
            'ajuste_plan' => '',
            'asignacion_recursos' => '',
            'preparativos_logisticos' => '',
            'acciones' => '',
            'realizadopor' => $realizadoPor ?? 0,
        ]);
    }


    public function data(int $id): void
    {
        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->with([
                'planAuditoria',
                'estacion',
            ])
            ->firstOrFail();

        $plan = $this->asegurarPlanAuditoria($auditoria);

        $usuarios = Usuario::query()
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->with('puesto')
            ->orderBy('nombre')
            ->get()
            ->map(function ($usuario) {
                return [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'puesto' => $usuario->puesto?->tipo_puesto ?? '',
                ];
            })
            ->values();

        $auditores = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->with('usuario')
            ->whereNotIn('categoria', [
                'GUÍAS',
                'OBSERVADORES',
                'EXPERTO(S) TÉCNICO(S)',
            ])
            ->orderBy('id')
            ->get()
            ->map(function ($auditor) {

                return [
                    'id' => $auditor->id,

                    'id_plan' => $auditor->id_plan,

                    'id_usuario' => $auditor->id_usuario,

                    'nombre' =>
                    $auditor->id_usuario
                        ? ($auditor->usuario?->nombre ?? '')
                        : $auditor->nombre,

                    'categoria' => $auditor->categoria,

                    'area_actividad' => $auditor->area_actividad,
                ];
            })
            ->values();

        $auxiliares = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->whereNull('id_usuario')
            ->whereIn('categoria', [
                'GUÍAS',
                'OBSERVADORES',
                'EXPERTO(S) TÉCNICO(S)',
            ])
            ->orderBy('id')
            ->get()
            ->map(function ($auxiliar) {

                return [
                    'id' => $auxiliar->id,
                    'id_plan' => $auxiliar->id_plan,
                    'id_usuario' => $auxiliar->id_usuario,
                    'nombre' => $auxiliar->nombre,
                    'categoria' => $auxiliar->categoria,
                ];
            })
            ->values();

        $agenda = PlanAuditoriaAgenda::query()
            ->where('id_plan', $plan->id)
            ->orderBy('hora_inicio')
            ->get()
            ->map(function ($agenda) {

                return [
                    'id' => $agenda->id,
                    'id_plan' => $agenda->id_plan,
                    'hora_inicio' => $agenda->hora_inicio,
                    'hora_termino' => $agenda->hora_termino,
                    'proceso' => $agenda->proceso,
                    'elemento_sistema' => $agenda->elemento_sistema,
                    'nombre_rol' => $agenda->nombre_rol,
                    'guia' => $agenda->guia,
                ];
            })
            ->values();

        $elementos = Elemento::query()
            ->orderBy('no')
            ->get()
            ->map(function ($elemento) {
                return [
                    'id' => $elemento->id,
                    'no' => $elemento->no,
                    'criterio' => $elemento->criterio,
                ];
            })
            ->values();

        $responsables = PlanAuditoriaResponsable::query()
            ->where('id_plan', $plan->id)
            ->with('usuario.puesto')
            ->orderBy('id')
            ->get()
            ->map(function ($responsable) {
                return [
                    'id' => $responsable->id,
                    'id_plan' => $responsable->id_plan,
                    'id_responsable' => $responsable->id_responsable,

                    'nombre' => $responsable->usuario?->nombre ?? '',
                    'puesto' => $responsable->usuario?->puesto?->tipo_puesto ?? '',
                ];
            })
            ->values();

        JsonResponse::custom([
            'success' => true,

            'data' => [
                'plan' => [
                    'id' => $plan->id,
                    'id_auditoria' => $plan->id_auditoria,

                    'razon_social' =>
                    $auditoria->estacion?->razonsocial,

                    'permiso_cre' =>
                    $auditoria->estacion?->permisocre,

                    'fecha' =>
                    $plan->fecha?->format('Y-m-d'),

                    'nom_director' =>
                    $plan->nom_director,

                    'ubicacion_instalacion' =>
                    $plan->ubicacion_instalacion,

                    'objetivo_auditoria' =>
                    $plan->objetivo_auditoria,

                    'alcance_auditoria' =>
                    $plan->alcance_auditoria,

                    'fecha_programada' =>
                    $plan->fecha_programada?->format('Y-m-d'),

                    'sitio' => $plan->sitio,

                    'metodo_auditoria' =>
                    $plan->metodo_auditoria,

                    'ajuste_plan' =>
                    $plan->ajuste_plan,

                    'asignacion_recursos' =>
                    $plan->asignacion_recursos,

                    'preparativos_logisticos' =>
                    $plan->preparativos_logisticos,

                    'acciones' =>
                    $plan->acciones,

                    'realizadopor' =>
                    $plan->realizadopor,
                ],

                'responsables' => $responsables,

                'usuarios' => $usuarios,

                'auditores' => $auditores,

                'externos' => [],

                'auxiliares' => $auxiliares,

                'elementos' => $elementos,

                'agenda' => $agenda,
            ],
        ]);
    }

    public function createResponsable()
    {

        $id = Request::jsonInput('id');
        $id_responsable = Request::jsonInput('id_responsable');

        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        $plan = $this->asegurarPlanAuditoria($auditoria);

        $usuario = Usuario::query()
            ->where('id', $id_responsable)
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->with('puesto')
            ->firstOrFail();

        $existe = PlanAuditoriaResponsable::query()
            ->where('id_plan', $plan->id)
            ->where('id_responsable', $usuario->id)
            ->exists();

        if ($existe) {

            JsonResponse::error(
                'El usuario ya está asignado al plan.',
            );

            return;
        }

        $responsable = PlanAuditoriaResponsable::create([
            'id_plan' => $plan->id,
            'id_responsable' => $usuario->id,
        ]);

        JsonResponse::success('Responsable creado', [
            'data' => [
                'id' => $responsable->id,
                'id_plan' => $responsable->id_plan,
                'id_responsable' => $responsable->id_responsable,
                'nombre' => $usuario->nombre,
                'puesto' => $usuario->puesto?->tipo_puesto ?? '',
            ],
        ]);
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
            'nom_director',
            'ubicacion_instalacion',
            'objetivo_auditoria',
            'alcance_auditoria',
            'fecha_programada',
            'sitio',
            'metodo_auditoria',
            'ajuste_plan',
            'asignacion_recursos',
            'preparativos_logisticos',
            'acciones',
        ];

        if (!in_array($campo, $camposPermitidos, true)) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Campo no permitido.',
            ]);

            return;
        }

        $plan = PlanAuditoria::query()
            ->where('id', $id)
            ->whereHas('auditoria', function ($query) {
                $query->where(
                    'id_estacion',
                    $this->estacionId()
                );
            })
            ->firstOrFail();

        $plan->{$campo} = $valor;

        $plan->save();

        JsonResponse::custom([
            'success' => true,
            'message' => 'Información actualizada correctamente.',
        ]);
    }

    public function createAuditor(): void
    {
        $id = Request::jsonInput('id');

        $categoria = Request::jsonInput('equipo');

        $area = Request::jsonInput('area_actividad');

        $nombreExterno = Request::jsonInput('auditor');

        $idUsuario = Request::jsonInput('auditor_interno');


        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();


        $plan = $this->asegurarPlanAuditoria($auditoria);


        $auditor = PlanAuditoriaAuditor::create([
            'id_plan' => $plan->id,
            'id_usuario' => $idUsuario ?: null,
            'nombre' => $nombreExterno ?: '',
            'area_actividad' => $area,
            'categoria' => $categoria,
        ]);


        // Obtener el usuario interno
        $usuario = null;

        if ($auditor->id_usuario) {

            $usuario = Usuario::query()
                ->where('id', $auditor->id_usuario)
                ->first();
        }


        JsonResponse::success(
            'Auditor agregado correctamente',
            [
                'data' => [
                    'id' => $auditor->id,
                    'id_plan' => $auditor->id_plan,
                    'id_usuario' => $auditor->id_usuario,

                    'nombre' => $auditor->id_usuario
                        ? ($usuario?->nombre ?? '')
                        : $auditor->nombre,

                    'categoria' => $auditor->categoria,

                    'area_actividad' => $auditor->area_actividad,
                ],
            ]
        );
    }

    public function deleteAuditor(): void
    {
        $id = Request::jsonInput('id');
        $auditor = PlanAuditoriaAuditor::query()
            ->where('id', $id)
            ->firstOrFail();

        $auditor->delete();

        JsonResponse::success('Auditor Eliminado');
    }

    public function createAuxiliar(): void
    {
        $id = Request::jsonInput('id');

        $categoria = Request::jsonInput('categoria');

        $nombre = Request::jsonInput('nombre');

        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        $plan = $this->asegurarPlanAuditoria($auditoria);

        $auxiliar = PlanAuditoriaAuditor::create([
            'id_plan' => $plan->id,
            'id_usuario' => null,
            'nombre' => $nombre,
            'area_actividad' => '',
            'categoria' => $categoria,
        ]);

        JsonResponse::success(
            'Auxiliar agregado correctamente',
            [
                'data' => [
                    'id' => $auxiliar->id,
                    'id_plan' => $auxiliar->id_plan,
                    'id_usuario' => null,
                    'nombre' => $auxiliar->nombre,
                    'categoria' => $auxiliar->categoria,
                ],
            ]
        );
    }

    public function deleteAuxiliar(): void
    {
        $id = Request::jsonInput('id');

        $auxiliar = PlanAuditoriaAuditor::query()
            ->where('id', $id)
            ->whereNull('id_usuario')
            ->whereIn('categoria', [
                'GUÍAS',
                'OBSERVADORES',
                'EXPERTO(S) TÉCNICO(S)',
            ])
            ->firstOrFail();

        $auxiliar->delete();

        JsonResponse::success(
            'Auxiliar eliminado correctamente.'
        );
    }

    public function createAgenda(): void
    {
        $id = Request::jsonInput('id');

        $horaInicio = Request::jsonInput('hora_inicio');
        $horaTermino = Request::jsonInput('hora_termino');
        $proceso = Request::jsonInput('proceso');
        $elementoSistema = Request::jsonInput('elemento_sistema');
        $nombreRol = Request::jsonInput('nombre_rol');
        $guia = Request::jsonInput('guia');

        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        $plan = $this->asegurarPlanAuditoria($auditoria);

        $agenda = PlanAuditoriaAgenda::create([
            'id_plan' => $plan->id,
            'hora_inicio' => $horaInicio,
            'hora_termino' => $horaTermino,
            'proceso' => $proceso,
            'elemento_sistema' => $elementoSistema,
            'nombre_rol' => $nombreRol,
            'guia' => $guia,
        ]);

        JsonResponse::success(
            'Agenda agregada correctamente',
            [
                'data' => [
                    'id' => $agenda->id,
                    'id_plan' => $agenda->id_plan,
                    'hora_inicio' => $agenda->hora_inicio,
                    'hora_termino' => $agenda->hora_termino,
                    'proceso' => $agenda->proceso,
                    'elemento_sistema' => $agenda->elemento_sistema,
                    'nombre_rol' => $agenda->nombre_rol,
                    'guia' => $agenda->guia,
                ],
            ]
        );
    }

    public function deleteAgenda(): void
    {
        $id = Request::jsonInput('id');


        $agenda = PlanAuditoriaAgenda::query()
            ->where('id', $id)
            ->firstOrFail();


        $agenda->delete();


        JsonResponse::success(
            'Agenda eliminada correctamente.'
        );
    }


    public function pdf(int $id): void
    {
        $auditoria = Auditoria::query()
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->with([
                'planAuditoria',
                'estacion',
            ])
            ->firstOrFail();

        $plan = $this->asegurarPlanAuditoria($auditoria);

        $realizadoPor = null;

        if ($plan->realizadopor) {
            $realizadoPor = Usuario::query()
                ->where('id', $plan->realizadopor)
                ->with('puesto')
                ->first();
        }

        $responsables = PlanAuditoriaResponsable::query()
            ->where('id_plan', $plan->id)
            ->with('usuario')
            ->orderBy('id')
            ->get();

        $auditores = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->with('usuario')
            ->whereNotIn('categoria', [
                'GUÍAS',
                'OBSERVADORES',
                'EXPERTO(S) TÉCNICO(S)',
            ])
            ->orderBy('id')
            ->get();

        $auxiliaresGuias = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->whereNull('id_usuario')
            ->where('categoria', 'GUÍAS')
            ->orderBy('id')
            ->get();

        $auxiliaresObservadores = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->whereNull('id_usuario')
            ->where('categoria', 'OBSERVADORES')
            ->orderBy('id')
            ->get();

        $auxiliaresExpertos = PlanAuditoriaAuditor::query()
            ->where('id_plan', $plan->id)
            ->whereNull('id_usuario')
            ->where('categoria', 'EXPERTO(S) TÉCNICO(S)')
            ->orderBy('id')
            ->get();

        $agenda = PlanAuditoriaAgenda::query()
            ->where('id_plan', $plan->id)
            ->orderBy('hora_inicio')
            ->get();

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '';

        $html .= '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Plan de Auditoría</title>';
        $html .= '
        <style>
         ' . $css . '
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: .8rem;
            color: #212529;
            margin: 0;
        }

           
        </style>';
        $html .= '</head>';
        $html .= '<body >';

        $html .= '
    <table class="table table-sm table-bordered">
        <tbody>

            <tr>
                <td width="33%" align="center" rowspan="2">
                    ' . htmlspecialchars(
            $auditoria->estacion?->razonsocial ?? ''
        ) . '
                </td>

                <td width="34%" align="center" rowspan="2">
                    <b>Plan de Auditoría</b>
                </td>

                <td width="33%" align="center">
                    <b>Fecha de autorización: 01-01-2024</b>
                </td>
            </tr>

            <tr>
                <td align="center">
                    Fo.SGM.018
                </td>
            </tr>

            <tr>

                <td align="center">
                    Realizado por:<br>
                    ' . htmlspecialchars(
            $realizadoPor?->nombre ?? ''
        ) . '
                </td>

                <td align="center">
                    Revisado por:<br>
                    Eduardo Galicia Flores
                </td>

                <td align="center">
                    Autorizado por:<br>
                    ' . htmlspecialchars(
            $auditoria->estacion?->apoderado_legal ?? ''
        ) . '
                </td>

            </tr>

        </tbody>
    </table>

    <br>
    ';

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>
                <td colspan="3" class="bg-secondary text-white">
                    <b>I. DATOS GENERALES DEL PERMISIONARIO</b>
                </td>
            </tr>

            <tr>
                <td class="bg-light">
                    NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:
                </td>

                <td class="bg-light">
                    Permiso CRE:
                </td>

                <td class="bg-light">
                    FECHA DE ELABORACIÓN:
                </td>
            </tr>

            <tr>

                <td class="bg-light">
                    ' . htmlspecialchars(
            $auditoria->estacion?->razonsocial ?? ''
        ) . '
                </td>

                <td class="bg-light">
                    ' . htmlspecialchars(
            $auditoria->estacion?->permisocre ?? ''
        ) . '
                </td>

                <td>
                    ' . (
            $plan->fecha
            ? formatearFecha($plan->fecha->format('Y-m-d'))
            : ''
        ) . '
                </td>

            </tr>

            <tr>

                <td class="bg-light">
                    NOMBRE DEL DIRECTOR (ALTA DIRECCIÓN):
                </td>

                <td colspan="2">
                    ' . htmlspecialchars(
            $plan->nom_director ?? ''
        ) . '
                </td>

            </tr>

            <tr>

                <td class="bg-light">
                    NOMBRE DEL(LOS) RESPONSABLE DEL SGM
                </td>

                <td colspan="2" cellpadding="0" class="p-0 m-0">

                    <table class="table table-sm table-bordered p-0 m-0">
    ';

        foreach ($responsables as $responsable) {

            $html .= '
                        <tr>
                            <td>
                                ' . htmlspecialchars(
                $responsable->usuario?->nombre ?? ''
            ) . '
                            </td>
                        </tr>
        ';
        }

        $html .= '
                    </table>

                </td>

            </tr>

            <tr>

                <td class="bg-light">
                    UBICACIÓN DE LA INSTALACIÓN
                </td>

                <td colspan="2">
                    ' . htmlspecialchars(
            $plan->ubicacion_instalacion ?? ''
        ) . '
                </td>

            </tr>

        </tbody>

    </table>

    ';

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>
                <td colspan="3" class="bg-secondary text-white">
                    <b>II. DATOS DEL AUDITOR</b>
                </td>
            </tr>

            <tr>
                <td class="bg-light">
                    EQUIPO AUDITOR
                </td>

                <td class="bg-light">
                    NOMBRE:
                </td>

                <td class="bg-light">
                    ÁREA/PROCESO/ACTIVIDAD QUE AUDITA:
                </td>
            </tr>
    ';

        foreach ($auditores as $auditor) {

            $nombre = $auditor->id_usuario
                ? ($auditor->usuario?->nombre ?? '')
                : ($auditor->nombre ?? '');

            $html .= '
            <tr>

                <td>
                    ' . htmlspecialchars(
                $auditor->categoria ?? ''
            ) . '
                </td>

                <td>
                    ' . htmlspecialchars(
                $nombre
            ) . '
                </td>

                <td>
                    ' . htmlspecialchars(
                $auditor->area_actividad ?? ''
            ) . '
                </td>

            </tr>
        ';
        }

        $html .= '
        </tbody>

    </table>

    ';

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>
                <td colspan="3" class="bg-secondary text-white">
                    <b>III DATOS DEL EQUIPO AUXILIAR DEL AUDITOR</b>
                </td>
            </tr>

            <tr>

                <td class="bg-light">
                    GUÍAS:
                </td>

                <td class="bg-light">
                    OBSERVADORES:
                </td>

                <td class="bg-light">
                    EXPERTO(S) TÉCNICO(S)
                </td>

            </tr>

            <tr>

                <td valign="top" class="m-0 p-0">

                    <table class="table table-sm table-border m-0 p-0">
    ';

        foreach ($auxiliaresGuias as $auxiliar) {

            $html .= '
                        <tr>
                            <td>
                                ' . htmlspecialchars(
                $auxiliar->nombre ?? ''
            ) . '
                            </td>
                        </tr>
        ';
        }

        $html .= '
                    </table>

                </td>

                <td valign="top">

                    <table class="table table-sm table-bordered">
    ';

        foreach ($auxiliaresObservadores as $auxiliar) {

            $html .= '
                        <tr>
                            <td>
                                ' . htmlspecialchars(
                $auxiliar->nombre ?? ''
            ) . '
                            </td>
                        </tr>
        ';
        }

        $html .= '
                    </table>

                </td>

                <td valign="top">

                    <table class="table table-sm table-bordered">
    ';

        foreach ($auxiliaresExpertos as $auxiliar) {

            $html .= '
                        <tr>
                            <td>
                                ' . htmlspecialchars(
                $auxiliar->nombre ?? ''
            ) . '
                            </td>
                        </tr>
        ';
        }

        $html .= '
                    </table>

                </td>

            </tr>

        </tbody>

    </table>

    ';

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>
                <td colspan="3" class="bg-secondary text-white"> 
                    <b>IV Auditoría</b>
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    OBJETIVOS DE LA AUDITORÍA.
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->objetivo_auditoria ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    ALCANCE DE LA AUDITORÍA.
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->alcance_auditoria ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    FECHA PROGRAMADA DE AUDITORIA
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . (
            $plan->fecha_programada
            ? formatearFecha($plan->fecha_programada->format('Y-m-d'))
            : ''
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    SITIO
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->sitio ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    MÉTODOS DE AUDITORÍA:
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->metodo_auditoria ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    AJUSTES AL PLAN:
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->ajuste_plan ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    ASIGNACIÓN DE RECURSOS APROPIADOS PARA LAS ÁREAS CRÍTICAS, CUANDO APLIQUE:
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->asignacion_recursos ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    PREPARATIVOS LOGÍSTICOS Y DE COMUNICACIONES
                    (Requisitos para el ingreso a las instalaciones,
                    medidas de seguridad, números de emergencia,
                    lugar de reunión de apertura, lugar de reunión de cierre,
                    transporte y otros requerimientos del Equipo Auditor,
                    como hospedaje, alimentos, entre otros):
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->preparativos_logisticos ?? ''
            )
        ) . '
                </td>
            </tr>

            <tr>
                <td colspan="3" class="bg-light">
                    ACCIONES DE SEGUIMIENTO A PARTIR DE LA INFORMACIÓN
                    GENERADA EN AUDITORÍAS PREVIAS.
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    ' . nl2br(
            htmlspecialchars(
                $plan->acciones ?? ''
            )
        ) . '
                </td>
            </tr>

        </tbody>

    </table>

    ';

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>

                <td colspan="5" class="bg-secondary text-white">

                    <b>V. AGENDA.</b><br>

                    <small>
                        Nota: Elaborar una Agenda para cada sitio a ser auditado.
                    </small>

                </td>

            </tr>

        </tbody>

    </table>


    <table class="table table-sm table-bordered">

        <thead>

            <tr>

                <th align="center" class="bg-light">
                    HORARIO
                </th>

                <th align="center" class="bg-light">
                    PROCESO
                </th>

                <th align="center" class="bg-light">
                    ELEMENTO DEL SISTEMA DE GESTION DE MEDICION
                </th>

                <th align="center" class="bg-light">
                    NOMBRE Y ROL DEL AUDITOR
                </th>

                <th align="center" class="bg-light">
                    GUÍA
                </th>

            </tr>

        </thead>

        <tbody>
    ';

        if ($agenda->count() > 0) {

            foreach ($agenda as $item) {

                $html .= '
                <tr>

                    <td align="center">
                        De ' .
                    htmlspecialchars(
                        $item->hora_inicio ?? ''
                    ) .
                    ' a ' .
                    htmlspecialchars(
                        $item->hora_termino ?? ''
                    ) .
                    '</td>

                    <td align="center">
                        ' . htmlspecialchars(
                        $item->proceso ?? ''
                    ) . '
                    </td>

                    <td align="center">
                        ' . htmlspecialchars(
                        $item->elemento_sistema ?? ''
                    ) . '
                    </td>

                    <td align="center">
                        ' . htmlspecialchars(
                        $item->nombre_rol ?? ''
                    ) . '
                    </td>

                    <td align="center">
                        ' . htmlspecialchars(
                        $item->guia ?? ''
                    ) . '
                    </td>

                </tr>
            ';
            }
        } else {

            $html .= '
            <tr>

                <td colspan="5" align="center">
                    No se encontró información para mostrar
                </td>

            </tr>
        ';
        }

        $html .= '
        </tbody>

    </table>

    ';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream(
            'Plan de Auditoria.pdf',
            [
                'Attachment' => true,
            ]
        );
    }
}
