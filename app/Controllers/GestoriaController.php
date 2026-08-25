<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Usuario;
use App\Models\Sasisopa\RequisitosLegalesLista;
use App\Models\Sasisopa\RequisitosLegalesDependencia;
use App\Models\Sasisopa\RequisitosLegalesMunAlcEst;
use App\Models\Sasisopa\RequisitosLegalesGobierno;

class GestoriaController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index()
    {
        $title = 'Gestoria';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/gestoria/index.datatable.init.js?v=1.1.0',
                '/js/gestoria/index.actions.init.js?v=1.1.0'
            ],
            'help' => false
        ];

        View::render('gestoria/index', $data, 'main');
    }

    public function requisitosLegales()
    {
        $title = 'Configuración Requisitos Legales';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $idEstacion = $this->estacionId();

        $personal = Usuario::query()
            ->where('id_puesto', 5)
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre'
            ]);

        $nivelesGobierno = RequisitosLegalesGobierno::query()
            ->whereIn('id_estacion', [$idEstacion, 0])
            ->where('estado', 1)
            ->orderBy('gobierno')
            ->get();

        $municipiosAlcaldiasEstados = RequisitosLegalesMunAlcEst::query()
            ->whereIn('id_estacion', [$idEstacion, 0])
            ->where('estado', 1)
            ->orderBy('mun_alc_est')
            ->get();

        $dependencias = RequisitosLegalesDependencia::query()
            ->whereIn('id_estacion', [$idEstacion, 0])
            ->where('estado', 1)
            ->orderBy('dependencia')
            ->get();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,

            'personal' => $personal,
            'nivelesGobierno' => $nivelesGobierno,
            'municipiosAlcaldiasEstados' => $municipiosAlcaldiasEstados,
            'dependencias' => $dependencias,

            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/gestoria/requisitos-legales/index.actions.init.js?v=1.9.0',
                '/js/gestoria/requisitos-legales/nivel-gobierno.datatable.init.js?v=1.3.0',
                '/js/gestoria/requisitos-legales/municipio-alcaldia-estado.datatable.init.js?v=1.2.0',
                '/js/gestoria/requisitos-legales/dependencias.datatable.init.js?v=1.2.0',
                '/js/gestoria/requisitos-legales/requisito-legal.datatable.init.js?v=1.2.0'
            ],

            'help' => false
        ];

        View::render(
            'gestoria/requisitos-legales',
            $data,
            'main'
        );
    }

    //------ Nivel de Gobierno

    public function datatableNivelGobierno()
    {
        $estaciones = RequisitosLegalesGobierno::where('estado', 1)
            ->orderBy('id', 'asc')->get();

        JsonResponse::custom([
            'data' => $estaciones
        ]);
    }

    public function createNivelGobierno(): void
    {
        $gobierno = RequisitosLegalesGobierno::create([
            'gobierno'    => Request::jsonInput('detalle'),
            'id_estacion' => 0,
            'disabled'    => 1,
            'estado'      => 1,
        ]);

        JsonResponse::success('Gobierno registrado correctamente.', [
            'id' => $gobierno->id
        ]);
    }

    public function deleteNivelGobierno(): void
    {
        $id = Request::jsonInput('id');

        // 1. Buscar el registro en la base de datos
        $gobierno = RequisitosLegalesGobierno::find($id);

        // 2. Responder 404 si el registro no existe
        if (!$gobierno) {
            JsonResponse::notFound('El registro no existe.');
        }

        // 3. Actualizar el estado
        $gobierno->update([
            'estado' => 0
        ]);

        // 4. Retornar respuesta exitosa
        JsonResponse::success('Gobierno desactivado correctamente.');
    }

    //------ Nivel de Gobierno

    //------ Municipio, Alcaldía y Estado
    public function datatableMunicipioAlcaldiaEstado()
    {

        $estaciones = RequisitosLegalesMunAlcEst::where('estado', 1)
            ->orderBy('id', 'asc')->get();

        JsonResponse::custom([
            'data' => $estaciones
        ]);
    }

    public function createMunicipioAlcaldiaEstado(): void
    {
        $munAlcEst = RequisitosLegalesMunAlcEst::create([
            'mun_alc_est' => Request::jsonInput('detalle'),
            'id_estacion' => 0,
            'disabled'    => 1,
            'estado'      => 1,
        ]);

        JsonResponse::success('Municipio, Alcaldía o Estado registrado correctamente.', [
            'id' => $munAlcEst->id
        ]);
    }

    public function deleteMunicipioAlcaldiaEstado(): void
    {
        $id = Request::jsonInput('id');

        // 1. Buscar el registro en la base de datos
        $munAlcEst = RequisitosLegalesMunAlcEst::find($id);

        // 2. Responder 404 si el registro no existe
        if (!$munAlcEst) {
            JsonResponse::notFound('El registro no fue encontrado.');
        }

        // 3. Actualizar el estado
        $munAlcEst->update([
            'estado' => 0
        ]);

        // 4. Retornar respuesta exitosa
        JsonResponse::success('Municipio, Alcaldía o Estado desactivado correctamente.');
    }
    //------ Municipio, Alcaldía y Estado

    //------ Dependencias
    public function datatableDependencias()
    {

        $estaciones = RequisitosLegalesDependencia::where('estado', 1)
            ->orderBy('id', 'asc')->get();

        JsonResponse::custom([
            'data' => $estaciones
        ]);
    }

    public function createDependencias(): void
    {
        $dependencia = RequisitosLegalesDependencia::create([
            'dependencia' => Request::jsonInput('detalle'),
            'id_estacion' => 0,
            'disabled'    => 1,
            'estado'      => 1,
        ]);

        JsonResponse::success('Dependencia registrada correctamente.', [
            'id' => $dependencia->id
        ]);
    }

    public function deleteDependencias(): void
    {
        $id = Request::jsonInput('id');

        // 1. Buscar el registro en la base de datos
        $dependencia = RequisitosLegalesDependencia::find($id);

        // 2. Responder 404 si el registro no existe
        if (!$dependencia) {
            JsonResponse::notFound('La dependencia no fue encontrada.');
        }

        // 3. Actualizar el estado
        $dependencia->update([
            'estado' => 0
        ]);

        // 4. Retornar respuesta exitosa
        JsonResponse::success('Dependencia desactivada correctamente.');
    }
    //------ Dependencias

    //------ Requisitos Legales

    public function datatableRequisitoLegal()
    {
        $requisitos = RequisitosLegalesGobierno::query()
            ->from('rl_requisitos_legales_lista as rl')
            ->leftJoin('tb_usuarios as u', 'u.id', '=', 'rl.id_usuario')
            ->where('rl.estado', 1)
            ->orderBy('rl.nivel_gobierno')
            ->orderBy('rl.mun_alc_est')
            ->select([
                'rl.id',
                'rl.nivel_gobierno',
                'rl.mun_alc_est',
                'rl.dependencia',
                'rl.permiso',
                'rl.fundamento',
                'rl.id_usuario',
                'u.nombre as responsable',
                'rl.sgm',
            ])
            ->get();

        JsonResponse::custom([
            'data' => $requisitos
        ]);
    }


    public function createRequisitoLegal(): void
    {
        RequisitosLegalesLista::create([
            'nivel_gobierno' => Request::jsonInput('NivelG'),
            'mun_alc_est'    => Request::jsonInput('MuAlEs'),
            'dependencia'    => Request::jsonInput('Dependencia'),
            'permiso'        => Request::jsonInput('Permiso'),
            'fundamento'     => Request::jsonInput('Fundamento'),
            'id_estacion'    => 0,
            'id_usuario'     => Request::jsonInput('IdPersonal'),
            'sgm'            => Request::jsonInput('sgmValor'),
            'disabled'       => 1,
            'estado'         => 1,
        ]);

        JsonResponse::success('Requisito legal registrado correctamente.');
    }

    public function deleteRequisitoLegal(): void
    {
        $id = Request::jsonInput('id');

        // 1. Buscar el registro en la base de datos
        $requisito = RequisitosLegalesLista::find($id);

        // 2. Responder 404 si el registro no existe
        if (!$requisito) {
            JsonResponse::notFound('El requisito legal no fue encontrado.');
        }

        // 3. Actualizar el estado
        $requisito->update([
            'estado' => 0
        ]);

        // 4. Retornar respuesta exitosa
        JsonResponse::success('Requisito legal desactivado correctamente.');
    }

    public function updateRequisitosLegales(): void
    {
        $idRequisito = Request::jsonInput('idRequisito');

        // 1. Buscar el registro en la base de datos
        $requisito = RequisitosLegalesLista::find($idRequisito);

        // 2. Retornar error 404 si el requisito no existe
        if (!$requisito) {
            JsonResponse::notFound('El requisito legal no fue encontrado.');
        }

        // 3. Actualizar los campos
        $requisito->update([
            'nivel_gobierno' => Request::jsonInput('NivelG'),
            'mun_alc_est'    => Request::jsonInput('MuAlEs'),
            'dependencia'    => Request::jsonInput('Dependencia'),
            'permiso'        => Request::jsonInput('Permiso'),
            'fundamento'     => Request::jsonInput('Fundamento'),
            'id_usuario'     => Request::jsonInput('IdPersonal'),
            'sgm'            => Request::jsonInput('sgmValor'),
        ]);

        // 4. Responder con éxito
        JsonResponse::success('Requisito legal actualizado correctamente.');
    }

    //-----
}
