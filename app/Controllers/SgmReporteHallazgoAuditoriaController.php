<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Elemento;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\PlanAuditoriaResponsable;
use App\Models\Sgm\HallazgoAuditoria;
use App\Models\Sgm\HallazgoAuditoriaResultado;
use App\Models\Sgm\HallazgoAuditoriaEntrevistador;
use App\Models\Sgm\HallazgoAuditoriaAuditor;
use App\Models\Sgm\HallazgoAuditoriaConforme;
use App\Models\Sgm\HallazgoAuditoriaMejora;
use App\Models\Sgm\HallazgoAuditoriaResponsable;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmReporteHallazgoAuditoriaController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index(int $id)
    {
        $title = 'Reporte e Hallazgos de Auditoria';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('10. Auditorias, Internas, externas y Atención de hallazgos', '/sgm/auditorias-internas-externas-atencion-hallazgos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $this->validaHallazgoAuditoria($this->estacionId(), $id);
        $this->validaResultados($id);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [],
            'scripts' => [
                '/js/sgm/auditorias/reportehallazgoauditoria.actions.init.js?v=1.9.0',
            ],
            'help' => true,
        ];

        View::render('sgm/auditorias/reporte-hallazgos-auditoria', $data, 'sgm');
    }

    private function validaHallazgoAuditoria(
        int $idEstacion,
        int $idRegistro
    ): void {


        $realizadoPor = Autorizado::query()
            ->where('estado', 1)
            ->whereHas('usuario', function ($query) use ($idEstacion) {
                $query->where('id_gas', $idEstacion);
            })
            ->value('id_usuario');

        $realizadoPor = $realizadoPor ?? 0;

        $existe = HallazgoAuditoria::query()
            ->where('id_auditoria', $idRegistro)
            ->exists();

        if (!$existe) {

            HallazgoAuditoria::query()->create([
                'id_auditoria' => $idRegistro,
                'fecha' => date('Y-m-d'),
                'fecha_ubicacion' => '',
                'objetivo_auditoria' => '',
                'alcance_auditoria' => '',
                'comentarios' => '',
                'nota' => '',
                'motivos' => '',
                'conclusiones' => '',
                'lugar_fecha' => '',
                'auditor_lider' => 0,
                'responsable_sgm' => 0,
                'realizadopor' => $realizadoPor,
            ]);
        }
    }

    private function validaResultados(int $idRegistro): void
    {

        $hallazgo = HallazgoAuditoria::query()
            ->where('id_auditoria', $idRegistro)
            ->firstOrFail();

        $existe = HallazgoAuditoriaResultado::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->exists();

        if (!$existe) {

            $elementos = Elemento::query()
                ->select('id')
                ->get();

            foreach ($elementos as $elemento) {

                HallazgoAuditoriaResultado::query()->create([
                    'id_hallazgo' => $hallazgo->id,
                    'id_elemento' => $elemento->id,
                    'resultado' => '',
                ]);
            }
        }
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

        $hallazgo = HallazgoAuditoria::query()
            ->where('id_auditoria', $id)
            ->firstOrFail();

        $nomAuditor = null;

        if ((int) $hallazgo->auditor_lider > 0) {

            $nomAuditor = Usuario::query()
                ->where('id', $hallazgo->auditor_lider)
                ->with('puesto')
                ->first();
        }

        $nomResponsable = null;

        if ((int) $hallazgo->responsable_sgm > 0) {

            $nomResponsable = Usuario::query()
                ->where('id', $hallazgo->responsable_sgm)
                ->with('puesto')
                ->first();
        }

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
                    'firma' => $usuario->firma ?? '',
                ];
            })
            ->values();

        $responsables = PlanAuditoriaResponsable::query()
            ->where('id_plan', $id)
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

        $entrevistados = HallazgoAuditoriaEntrevistador::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with('usuario')
            ->orderBy('id')
            ->get()
            ->map(function ($entrevistado) {

                $tieneUsuario = !is_null($entrevistado->id_usuario)
                    && (int) $entrevistado->id_usuario !== 0;

                return [
                    'id' =>
                    $entrevistado->id,

                    'id_hallazgo' =>
                    $entrevistado->id_hallazgo,

                    'id_usuario' =>
                    $entrevistado->id_usuario,

                    'nombre' =>
                    $tieneUsuario
                        ? ($entrevistado->usuario?->nombre ?? '')
                        : ($entrevistado->nombre ?? ''),

                    'puesto' =>
                    $tieneUsuario
                        ? ($entrevistado->usuario?->puesto?->tipo_puesto ?? '')
                        : ($entrevistado->puesto ?? ''),

                    'area_descripcion' =>
                    $entrevistado->area_descripcion ?? '',
                ];
            })
            ->values();

        $equipoAuditor = HallazgoAuditoriaAuditor::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with('usuario.puesto')
            ->orderBy('id')
            ->get()
            ->map(function ($auditor) {

                // Si existe usuario relacionado
                if ((int) $auditor->id_usuario > 0) {

                    return [
                        'id' => $auditor->id,

                        'id_hallazgo' =>
                        $auditor->id_hallazgo,

                        'id_usuario' =>
                        $auditor->id_usuario,

                        'nombre' =>
                        $auditor->usuario?->nombre
                            ?? $auditor->nombre
                            ?? '',

                        'rol' =>
                        $auditor->usuario?->puesto?->tipo_puesto
                            ?? $auditor->rol
                            ?? '',
                    ];
                }

                // Registro capturado manualmente
                return [
                    'id' => $auditor->id,

                    'id_hallazgo' =>
                    $auditor->id_hallazgo,

                    'id_usuario' =>
                    $auditor->id_usuario,

                    'nombre' =>
                    $auditor->nombre ?? '',

                    'rol' =>
                    $auditor->rol ?? '',
                ];
            })
            ->values();

        $resultados = HallazgoAuditoriaResultado::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with('elemento')
            ->orderBy('id')
            ->get()
            ->map(function ($resultado) {
                return [
                    'id' =>
                    $resultado->id,

                    'id_elemento' =>
                    $resultado->id_elemento,

                    'resultado' =>
                    $resultado->resultado ?? '',

                    'no' =>
                    $resultado->elemento?->no ?? '',

                    'criterio' =>
                    $resultado->elemento?->criterio ?? '',
                ];
            })
            ->values();

        $conformes = HallazgoAuditoriaConforme::where(
            'id_hallazgo',
            $hallazgo->id
        )
            ->get([
                'id',
                'id_hallazgo',
                'descripcion',
                'evidencia',
                'criterio',
            ]);

        $mejoras = HallazgoAuditoriaMejora::where(
            'id_hallazgo',
            $hallazgo->id
        )
            ->get([
                'id',
                'id_hallazgo',
                'descripcion',
            ]);

        JsonResponse::custom([
            'success' => true,

            'data' => [

                'id' =>
                $hallazgo->id,

                'razon_social' =>
                $auditoria->estacion?->razonsocial,

                'permiso_cre' =>
                $auditoria->estacion?->permisocre,

                'fecha' =>
                $hallazgo->fecha->format('Y-m-d'),

                'fecha_ubicacion' =>
                $hallazgo->fecha_ubicacion,

                'objetivo_auditoria' =>
                $hallazgo->objetivo_auditoria,

                'alcance_auditoria' =>
                $hallazgo->alcance_auditoria,

                'comentarios' =>
                $hallazgo->comentarios,

                'nota' =>
                $hallazgo->nota,

                'motivos' =>
                $hallazgo->motivos,

                'conclusiones' =>
                $hallazgo->conclusiones,

                'lugar_fecha' =>
                $hallazgo->lugar_fecha,

                'auditor_lider' =>
                $hallazgo->auditor_lider,

                'nom_auditor' =>
                $nomAuditor,

                'responsable_sgm' =>
                $hallazgo->responsable_sgm,

                'nom_responsable' =>
                $nomResponsable,

                'realizadopor' =>
                $hallazgo->realizadopor,

                'responsables' =>
                $responsables,

                'usuarios' =>
                $usuarios,

                'entrevistados' =>
                $entrevistados,

                'equipoauditor' => $equipoAuditor,

                'resultados' =>
                $resultados,

                'conformes' => $conformes,
                'mejoras' => $mejoras
            ],
        ]);
    }

    public function editar(): void
    {
        $id = (int) Request::input('id');
        $campo = Request::input('campo');
        $valor = Request::input('valor');

        $camposPermitidos = [
            'fecha',
            'fecha_ubicacion',
            'objetivo_auditoria',
            'alcance_auditoria',
            'comentarios',
            'nota',
            'motivos',
            'conclusiones',
            'lugar_fecha',
            'auditor_lider',
            'responsable_sgm',
            'realizadopor',
        ];

        if (!in_array($campo, $camposPermitidos, true)) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Campo no permitido.',
            ]);

            return;
        }

        $plan = HallazgoAuditoria::query()
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

    public function createResponsable(): void
    {
        $id = (int) Request::jsonInput('id');
        $idResponsable = (int) Request::jsonInput('id_responsable');


        $usuario = Usuario::query()
            ->where('id', $idResponsable)
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->with('puesto')
            ->firstOrFail();

        $existe = PlanAuditoriaResponsable::query()
            ->where('id_plan', $id)
            ->where('id_responsable', $usuario->id)
            ->exists();

        if ($existe) {

            JsonResponse::error(
                'El usuario ya está asignado al plan.'
            );

            return;
        }

        $responsable = PlanAuditoriaResponsable::create([
            'id_plan' => $id,
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

    public function agregarEntrevistado(): void
    {

        $id = (int) Request::jsonInput('id');
        $idUsuario = (int) Request::jsonInput('id_usuario');
        $area = Request::jsonInput('area_descripcion');

        $entrevistado = HallazgoAuditoriaEntrevistador::query()
            ->create([
                'id_hallazgo' =>
                $id,

                'id_usuario' =>
                $idUsuario,

                'nombre' =>
                '',

                'puesto' =>
                '',

                'area_descripcion' =>
                $area
            ]);

        $entrevistado->load('usuario.puesto');

        $tieneUsuario =
            !is_null($entrevistado->id_usuario)
            && (int) $entrevistado->id_usuario !== 0;

        JsonResponse::custom([
            'success' => true,

            'data' => [

                'id' =>
                $entrevistado->id,

                'id_hallazgo' =>
                $entrevistado->id_hallazgo,

                'id_usuario' =>
                $entrevistado->id_usuario,

                'nombre' =>
                $tieneUsuario
                    ? ($entrevistado->usuario?->nombre ?? '')
                    : ($entrevistado->nombre ?? ''),

                'puesto' =>
                $tieneUsuario
                    ? ($entrevistado->usuario?->puesto?->tipo_puesto ?? '')
                    : ($entrevistado->puesto ?? ''),

                'area_descripcion' =>
                $entrevistado->area_descripcion ?? '',
            ],
        ]);
    }

    public function eliminarEntrevistado(): void
    {

        $id = Request::jsonInput('id');
        $entrevistado = HallazgoAuditoriaEntrevistador::query()
            ->findOrFail($id);

        $entrevistado->delete();

        JsonResponse::success('Se elimino el entrevistado');
    }


    public function agregarEquipoAuditor(): void
    {

        $id = Request::jsonInput('id');
        $idusuario = Request::jsonInput('id_usuario');
        $nombre = Request::jsonInput('nombre');
        $rol = Request::jsonInput('rol');


        $hallazgo = HallazgoAuditoria::query()
            ->where('id', $id)
            ->firstOrFail();

        if (!empty($idusuario)) {

            $auditor = HallazgoAuditoriaAuditor::query()
                ->create([

                    'id_hallazgo' =>
                    $hallazgo->id,

                    'id_usuario' =>
                    $idusuario,

                    'nombre' =>
                    '',

                    'rol' =>
                    '',

                ]);
        } else {

            $auditor = HallazgoAuditoriaAuditor::query()
                ->create([

                    'id_hallazgo' =>
                    $hallazgo->id,

                    'nombre' =>
                    $nombre,

                    'rol' =>
                    $rol,

                ]);
        }

        $auditor->load('usuario.puesto');

        JsonResponse::custom([
            'success' => true,

            'data' => [

                'id' =>
                $auditor->id,

                'id_hallazgo' =>
                $auditor->id_hallazgo,

                'id_usuario' =>
                $idusuario,

                'nombre' =>
                $auditor->usuario?->nombre
                    ?: ($auditor->nombre ?? ''),

                'rol' =>
                $auditor->usuario?->puesto?->tipo_puesto
                    ?: ($auditor->rol ?? ''),

            ],
        ]);
    }

    public function eliminarEquipoAuditor(): void
    {
        $id = Request::jsonInput('id');

        $auditor = HallazgoAuditoriaAuditor::query()
            ->findOrFail($id);

        $auditor->delete();
        JsonResponse::success('Se elimino Auditor');
    }

    public function actualizarResultado()
    {

        $id = Request::jsonInput('id');
        $resultado = Request::jsonInput('resultado');

        HallazgoAuditoriaResultado::where('id', $id)
            ->update([
                'resultado' => $resultado,
            ]);

        JsonResponse::success('Resultado actualizado');
    }

    public function agregarConforme()
    {

        $id = Request::jsonInput('id');
        $descripcion = Request::jsonInput('descripcion');
        $evidencia = Request::jsonInput('evidencia');
        $criterio = Request::jsonInput('criterio');

        $conforme = HallazgoAuditoriaConforme::create([
            'id_hallazgo' => $id,
            'descripcion' => $descripcion,
            'evidencia' => $evidencia,
            'criterio' => $criterio,
        ]);

        JsonResponse::custom([
            'success' => true,

            'data' => [

                'id' =>
                $conforme->id,

                'id_hallazgo' =>
                $conforme->id_hallazgo,

                'descripcion' =>
                $conforme->descripcion,

                'evidencia' =>
                $conforme->evidencia,

                'criterio' =>
                $conforme->criterio,

            ],
        ]);
    }

    public function eliminarConforme()
    {
        $id = Request::jsonInput('id');
        $conforme = HallazgoAuditoriaConforme::findOrFail($id);

        $conforme->delete();

        JsonResponse::success('Documentación del hallazgo eliminada correctamente');
    }


    public function agregarMejora()
    {
        $id = Request::jsonInput('id');
        $descripcion = Request::jsonInput('descripcion');

        $mejora = HallazgoAuditoriaMejora::create([
            'id_hallazgo' => $id,
            'descripcion' => $descripcion,
        ]);

        JsonResponse::custom([
            'success' => true,

            'data' => [

                'id' =>
                $mejora->id,

                'id_hallazgo' =>
                $mejora->id_hallazgo,

                'descripcion' =>
                $mejora->descripcion

            ],
        ]);
    }

    public function eliminarMejora()
    {

        $id = Request::jsonInput('id');
        $mejora = HallazgoAuditoriaMejora::findOrFail($id);

        $mejora->delete();

        JsonResponse::success('Oportunidad de mejora eliminada correctamente');
    }

    public function pdf(int $id): void
    {

        $estacion = Estacion::find($this->estacionId());

        $hallazgo = HallazgoAuditoria::query()
            ->where('id_auditoria', $id)
            ->firstOrFail();


        /* --------------------------------------------------------------------------
     | USUARIOS
     | -------------------------------------------------------------------------- */

        $usuarioIds = collect([
            $hallazgo->realizadopor,
            $hallazgo->auditor_lider,
            $hallazgo->responsable_sgm,
        ])
            ->filter()
            ->unique()
            ->values();

        $usuarios = Usuario::query()
            ->whereIn('id', $usuarioIds)
            ->with('puesto')
            ->get()
            ->keyBy('id');

        $realizadoPor = $usuarios->get($hallazgo->realizadopor);
        $auditorLider = $usuarios->get($hallazgo->auditor_lider);
        $responsableSgm = $usuarios->get($hallazgo->responsable_sgm);


        /* --------------------------------------------------------------------------
     | RESPONSABLES DEL SGM
     | -------------------------------------------------------------------------- */

        $responsablesSgm = HallazgoAuditoriaResponsable::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with([
                'usuario.puesto',
            ])
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | PERSONAL ENTREVISTADO
     | -------------------------------------------------------------------------- */

        $entrevistados = HallazgoAuditoriaEntrevistador::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with([
                'usuario.puesto',
            ])
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | EQUIPO AUDITOR
     | -------------------------------------------------------------------------- */

        $equipoAuditor = HallazgoAuditoriaAuditor::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with([
                'usuario.puesto',
            ])
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | RESULTADOS DE AUDITORÍA
     | -------------------------------------------------------------------------- */

        $resultados = HallazgoAuditoriaResultado::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->with([
                'elemento',
            ])
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | HALLAZGOS NO CONFORMES
     | -------------------------------------------------------------------------- */

        $conformes = HallazgoAuditoriaConforme::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | OPORTUNIDADES DE MEJORA
     | -------------------------------------------------------------------------- */

        $mejoras = HallazgoAuditoriaMejora::query()
            ->where('id_hallazgo', $hallazgo->id)
            ->orderBy('id')
            ->get();


        /* --------------------------------------------------------------------------
     | FECHA
     | -------------------------------------------------------------------------- */

        $fecha = 'S/I';

        if (
            !empty($hallazgo->fecha) &&
            $hallazgo->fecha !== '0000-00-00'
        ) {

            $fecha = formatearFecha(
                $hallazgo->fecha instanceof \Carbon\Carbon
                    ? $hallazgo->fecha->format('Y-m-d')
                    : $hallazgo->fecha
            );
        }


        /* --------------------------------------------------------------------------
     | FIRMAS
     | -------------------------------------------------------------------------- */

        $baseFirmaAL = null;

        if ($auditorLider?->firma) {

            $rutaFirmaAL =
                ROOT_PATH .
                '/public/uploads/firma-personal/' .
                $auditorLider->firma;

            if (is_file($rutaFirmaAL)) {

                $dataFirmaAL = file_get_contents($rutaFirmaAL);

                if ($dataFirmaAL !== false) {

                    $baseFirmaAL =
                        'data:image/png;base64,' .
                        base64_encode($dataFirmaAL);
                }
            }
        }


        $baseFirmaRSGM = null;

        if ($responsableSgm?->firma) {

            $rutaFirmaRSGM =
                ROOT_PATH .
                '/public/uploads/firma-personal/' .
                $responsableSgm->firma;

            if (is_file($rutaFirmaRSGM)) {

                $dataFirmaRSGM = file_get_contents($rutaFirmaRSGM);

                if ($dataFirmaRSGM !== false) {

                    $baseFirmaRSGM =
                        'data:image/png;base64,' .
                        base64_encode($dataFirmaRSGM);
                }
            }
        }

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '';

        $html .= '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Reporte de Hallazgos de Auditoría</title>';
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
        $html .= '<body>';
        $html .= '
    <div>

    <table class="table table-bordered">

        <tbody>

            <tr>

                <td
                    class="align-middle text-center"
                    rowspan="2">

                    ' .
            htmlspecialchars(
                $estacion->razonsocial ?? ''
            ) .
            '

                </td>

                <td
                    class="align-middle text-center"
                    rowspan="2">

                    <b>
                        Reporte de Hallazgos de Auditoría
                    </b>

                </td>

                <td
                    class="align-middle text-center">

                    <b>
                        Fecha de autorización: 01-01-2024
                    </b>

                </td>

            </tr>


            <tr>

                <td
                    class="align-middle text-center">

                    Fo.SGM.019

                </td>

            </tr>


            <tr>

                <td
                    class="align-middle text-center">

                    Realizado por:
                    ' .
            htmlspecialchars(
                $realizadoPor?->nombre ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '

                </td>


                <td
                    class="align-middle text-center">

                    Revisado por:<br>
                    Eduardo Galicia Flores

                </td>


                <td
                    class="align-middle text-center">

                    Autorizado por:<br>
                    ' .
            htmlspecialchars(
                $estacion->apoderado_legal ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '

                </td>

            </tr>

        </tbody>

    </table>
    ';

        $html .= '
    <table class="table table-bordered table-sm">

        <tbody>

            <tr>

                <td
                    colspan="3"
                    class="bg-secondary text-white">

                    <b>
                        I. DATOS GENERALES DEL PERMISIONARIO
                    </b>

                </td>

            </tr>


            <tr>

                <td class="align-middle bg-light">
                    NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:
                </td>

                <td class="align-middle bg-light">
                    PERMISO CRE:
                </td>

                <td class="align-middle bg-light">
                    FECHA DE ELABORACIÓN:
                </td>

            </tr>


            <tr>

                <td class="align-middle bg-light">
                    ' .
            htmlspecialchars(
                $estacion->razonsocial ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '
                </td>

                <td class="align-middle bg-light">
                    ' .
            htmlspecialchars(
                $estacion->permisocre ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '
                </td>

                <td class="align-middle">
                    ' .
            htmlspecialchars(
                $fecha,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '
                </td>

            </tr>


            <tr>

                <td
                    class="align-middle bg-light">

                    NOMBRES DEL RESPONSABLE DEL SGM:

                </td>


                <td
                    colspan="2"
                    class="p-0 m-0">

                    <table
                        class="table table-sm table-bordered p-0 m-0">

                        <tbody>
    ';


        foreach ($responsablesSgm as $responsable) {

            $html .= '
                            <tr>

                                <td>

                                    <small>
                                        ' .
                htmlspecialchars(
                    $responsable->usuario?->nombre ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '
                                    </small>

                                </td>

                            </tr>
        ';
        }


        if ($responsablesSgm->isEmpty()) {

            $html .= '
                            <tr>

                                <td class="text-center">

                                    No se encontró información para mostrar

                                </td>

                            </tr>
        ';
        }


        $html .= '
                        </tbody>

                    </table>

                </td>

            </tr>

        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | DATOS DE LA AUDITORÍA
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>

                <td
                    colspan="2"
                    class="bg-secondary text-white">

                    <b>
                        I. DATOS DE LA AUDITORÍA
                    </b>

                </td>

            </tr>


            <tr>

                <td class="bg-light">
                    FECHA Y UBICACIÓN DE LA AUDITORÍA:
                </td>

                <td>
                    ' .
            htmlspecialchars(
                $hallazgo->fecha_ubicacion ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '
                </td>

            </tr>


            <tr>

                <td class="bg-light">
                    OBJETIVO DE LA AUDITORÍA:
                </td>

                <td>
                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->objetivo_auditoria ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '
                </td>

            </tr>


            <tr>

                <td class="bg-light">
                    ALCANCE DE LA AUDITORÍA:
                </td>

                <td>
                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->alcance_auditoria ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '
                </td>

            </tr>

        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | PERSONAL ENTREVISTADO
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>

                <td
                    colspan="3"
                    class="bg-secondary text-white text-center">

                    PERSONAL ENTREVISTADO

                </td>

            </tr>


            <tr class="bg-light">

                <td>NOMBRE</td>

                <td>PUESTO</td>

                <td>ÁREA DE ADSCRIPCIÓN</td>

            </tr>
    ';


        foreach ($entrevistados as $entrevistado) {

            $nombreEntrevistado = '';
            $puestoEntrevistado = '';

            if (
                !empty($entrevistado->id_usuario) &&
                (int) $entrevistado->id_usuario !== 0
            ) {

                $nombreEntrevistado =
                    $entrevistado->usuario?->nombre ?? '';

                $puestoEntrevistado =
                    $entrevistado->usuario?->puesto?->tipo_puesto ?? '';
            } else {

                $nombreEntrevistado =
                    $entrevistado->nombre ?? '';

                $puestoEntrevistado =
                    $entrevistado->puesto ?? '';
            }


            $html .= '
            <tr>

                <td class="align-middle text-center">

                    ' .
                htmlspecialchars(
                    $nombreEntrevistado,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '

                </td>

                <td class="align-middle text-center">

                    ' .
                htmlspecialchars(
                    $puestoEntrevistado,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '

                </td>

                <td class="align-middle text-center">

                    ' .
                htmlspecialchars(
                    $entrevistado->area_descripcion ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '

                </td>

            </tr>
        ';
        }


        if ($entrevistados->isEmpty()) {

            $html .= '
            <tr>

                <td
                    colspan="3"
                    class="text-center">

                    No se encontró información para mostrar

                </td>

            </tr>
        ';
        }


        $html .= '
        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | EQUIPO AUDITOR
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>

                <td
                    colspan="2"
                    class="bg-secondary text-white text-center">

                    EQUIPO AUDITOR

                </td>

            </tr>


            <tr class="bg-light">

                <td>NOMBRE</td>

                <td>
                    ROL (AUDITOR LÍDER, AUDITOR EXPERTO TÉCNICO,
                    AUDITOR ESPECIALISTA)
                </td>

            </tr>
    ';


        foreach ($equipoAuditor as $auditor) {

            $nombreAuditor = '';
            $rolAuditor = '';

            if (
                !empty($auditor->id_usuario) &&
                (int) $auditor->id_usuario !== 0
            ) {

                $nombreAuditor =
                    $auditor->usuario?->nombre ?? '';

                $rolAuditor =
                    $auditor->usuario?->puesto?->tipo_puesto ?? '';
            } else {

                $nombreAuditor =
                    $auditor->nombre ?? '';

                $rolAuditor =
                    $auditor->rol ?? '';
            }


            $html .= '
            <tr>

                <td class="align-middle">

                    ' .
                htmlspecialchars(
                    $nombreAuditor,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '

                </td>

                <td class="align-middle">

                    ' .
                htmlspecialchars(
                    $rolAuditor,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '

                </td>

            </tr>
        ';
        }


        if ($equipoAuditor->isEmpty()) {

            $html .= '
            <tr>

                <td
                    colspan="2"
                    class="text-center">

                    No se encontró información para mostrar

                </td>

            </tr>
        ';
        }


        $html .= '
        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | II. RESULTADO DE LA AUDITORÍA
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr>

                <td
                    colspan="3"
                    class="bg-secondary text-white">

                    <b>
                        II. RESULTADO DE LA AUDITORÍA
                    </b>

                </td>

            </tr>


            <tr>

                <td
                    colspan="3"
                    class="bg-light text-center">

                    ¿Durante la auditoría se revisaron los siguientes
                    elementos?<br>

                    Marcar el resultado como
                    C= Conforme,
                    NC= No Conforme,
                    OM= Oportunidad de Mejora

                </td>

            </tr>


            <tr class="bg-light">

                <td>No.</td>

                <td>CRITERIO:</td>

                <td>RESULTADO:</td>

            </tr>
    ';


        foreach ($resultados as $resultado) {

            $resultadoTexto = '';

            if ($resultado->resultado === 'C') {

                $resultadoTexto = 'C= Conforme';
            } elseif ($resultado->resultado === 'NC') {

                $resultadoTexto = 'NC= No Conforme';
            } elseif ($resultado->resultado === 'OM') {

                $resultadoTexto = 'OM= Oportunidad de Mejora';
            }


            $html .= '
            <tr>

                <td>
                    ' .
                htmlspecialchars(
                    $resultado->elemento?->no ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '
                </td>

                <td>
                    ' .
                htmlspecialchars(
                    $resultado->elemento?->criterio ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '
                </td>

                <td>
                    ' .
                htmlspecialchars(
                    $resultado->resultado ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '
                </td>

            </tr>
        ';
        }


        if ($resultados->isEmpty()) {

            $html .= '
            <tr>

                <td
                    colspan="3"
                    class="text-center">

                    No se encontró información para mostrar

                </td>

            </tr>
        ';
        }


        $html .= '
        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-bordered table-sm">

        <tbody>

            <tr>

                <td
                    colspan="4"
                    class="bg-secondary text-white">

                    <b>
                        III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
                    </b>

                </td>

            </tr>


            <tr class="bg-light">

                <td>No.</td>

                <td>DESCRIPCIÓN DEL HALLAZGO</td>

                <td>EVIDENCIA</td>

                <td>CRITERIO</td>

            </tr>
    ';


        foreach ($conformes as $index => $conforme) {

            $html .= '
            <tr>

                <td class="align-middle">

                    ' .
                ($index + 1) .
                '

                </td>

                <td class="align-middle">

                    ' .
                nl2br(
                    htmlspecialchars(
                        $conforme->descripcion ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) .
                '

                </td>

                <td class="align-middle">

                    ' .
                nl2br(
                    htmlspecialchars(
                        $conforme->evidencia ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) .
                '

                </td>

                <td class="align-middle">

                    ' .
                nl2br(
                    htmlspecialchars(
                        $conforme->criterio ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) .
                '

                </td>

            </tr>
        ';
        }


        if ($conformes->isEmpty()) {

            $html .= '
            <tr>

                <td
                    colspan="4"
                    class="text-center">

                    No se encontró información para mostrar

                </td>

            </tr>
        ';
        }


        $html .= '
        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | IV. OPORTUNIDADES DE MEJORA / OBSERVACIONES
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-bordered table-sm">

        <tbody>

            <tr>

                <td
                    colspan="2"
                    class="bg-secondary text-white">

                    <b>
                        IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES
                    </b>

                </td>

            </tr>


            <tr class="bg-light">

                <td>No.</td>

                <td>DESCRIPCIÓN</td>

            </tr>
    ';


        foreach ($mejoras as $index => $mejora) {

            $html .= '
            <tr>

                <td class="align-middle">

                    ' .
                ($index + 1) .
                '

                </td>

                <td class="align-middle">

                    ' .
                nl2br(
                    htmlspecialchars(
                        $mejora->descripcion ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) .
                '

                </td>

            </tr>
        ';
        }


        if ($mejoras->isEmpty()) {

            $html .= '
            <tr>

                <td
                    colspan="2"
                    class="text-center">

                    No se encontró información para mostrar

                </td>

            </tr>
        ';
        }


        $html .= '
        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | V. COMENTARIOS / VI. CONCLUSIONES
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-bordered table-sm">

        <tbody>

            <tr>

                <td
                    colspan="2"
                    class="bg-secondary text-white">

                    <b>
                        V. COMENTARIOS
                    </b>

                </td>

            </tr>


            <tr>

                <td colspan="2">

                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->comentarios ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '

                </td>

            </tr>


            <tr>

                <td
                    colspan="2"
                    class="bg-light">

                    NOTA: EN CASO DE QUE DURANTE LA AUDITORÍA,
                    EL EQUIPO AUDITOR DETECTE UNA SITUACIÓN DE RIESGO
                    PARA LA SEGURIDAD INDUSTRIAL, SEGURIDAD OPERATIVA
                    O PARA EL MEDIO AMBIENTE EN LAS INSTALACIONES DEL
                    REGULADO, DEBERÁ REPORTARLA EN ESTA SECCIÓN.

                </td>

            </tr>


            <tr>

                <td colspan="2">

                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->nota ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '

                </td>

            </tr>


            <tr>

                <td
                    colspan="2"
                    class="bg-light">

                    MOTIVOS DE FINALIZACIÓN DE AUDITORÍA
                    ANTES DE TIEMPO (SI APLICA):

                </td>

            </tr>


            <tr>

                <td colspan="2">

                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->motivos ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '

                </td>

            </tr>


            <tr>

                <td
                    colspan="2"
                    class="bg-secondary text-white">

                    <b>
                        VI. CONCLUSIONES
                    </b>

                </td>

            </tr>


            <tr>

                <td colspan="2">

                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->conclusiones ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '

                </td>

            </tr>

        </tbody>

    </table>
    ';


        /* --------------------------------------------------------------------------
     | LUGAR, FECHA Y FIRMAS
     | -------------------------------------------------------------------------- */

        $html .= '
    <table class="table table-sm table-bordered">

        <tbody>

            <tr class="bg-light">

                <td colspan="2">

                    LUGAR Y FECHA:

                </td>

            </tr>


            <tr>

                <td colspan="2">

                    ' .
            nl2br(
                htmlspecialchars(
                    $hallazgo->lugar_fecha ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) .
            '

                </td>

            </tr>


            <tr class="bg-light">

                <td class="text-center">

                    AUDITOR LIDER

                </td>

                <td class="text-center">

                    RESPONSABLE DEL SGM

                </td>

            </tr>


            <tr>

                <td class="text-center">
    ';


        if ($baseFirmaAL) {

            $html .= '
                    <div>

                        <img
                            src="' . $baseFirmaAL . '"
                            style="width: 100px;">

                    </div>
        ';
        }


        $html .= '
                    <b>

                        ' .
            htmlspecialchars(
                $auditorLider?->nombre ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '

                    </b>


                    <div>

                        <small>
                            NOMBRE COMPLETO Y FIRMA
                        </small>

                    </div>

                </td>


                <td class="text-center">
    ';


        if ($baseFirmaRSGM) {

            $html .= '
                    <div>

                        <img
                            src="' . $baseFirmaRSGM . '"
                            style="width: 100px;">

                    </div>
        ';
        }


        $html .= '
                    <b>

                        ' .
            htmlspecialchars(
                $responsableSgm?->nombre ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) .
            '

                    </b>


                    <div>

                        <small>
                            RECIBÍ DE CONFORMIDAD:
                            NOMBRE COMPLETO Y FIRMA
                        </small>

                    </div>

                </td>

            </tr>

        </tbody>

    </table>

    </div>
    ';


        /* --------------------------------------------------------------------------
     | CIERRE HTML
     | -------------------------------------------------------------------------- */

        $html .= '</body>';
        $html .= '</html>';


        /* --------------------------------------------------------------------------
     | DOMPDF
     | -------------------------------------------------------------------------- */

        $dompdf = new \Dompdf\Dompdf();

        $dompdf->loadHtml($html);

        $dompdf->setPaper(
            'A4',
            'portrait'
        );

        $dompdf->render();

        $dompdf->stream(
            'Reporte de Hallazgos de Auditoria.pdf',
            [
                'Attachment' => true,
            ]
        );
    }
}
