<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Services\RecibosNominaService;
use App\Services\KpiRecibosNominaService;
use App\Services\DropdownYearMesService;
use App\Services\ModuleStationService;
use App\Models\Operativo\OpReciboNominaV2;
use App\Models\Operativo\OpReciboNominaV2Comentario;
use App\Models\Operativo\OpReciboNominaAguinaldo;
use App\Models\Operativo\OpReciboNominaPeriodoAcuse;
use App\Models\Operativo\RhLocalidad;

class RecibosNominaController extends BaseController
{
    public const MODULE_KEY = 'recibos-nomina';

    /**
     * Resuelve la localidad (estacion o departamento) que se va a operar y la
     * valida contra lo que el usuario tiene permitido en este modulo.
     *
     * El id llega desde el navegador, asi que nunca se usa tal cual: se resuelve
     * desde el contexto cuando no viene en el request y despues se contrasta
     * contra la lista de localidades permitidas. Devuelve 0 cuando el usuario
     * todavia no ha elegido nada en el selector.
     *
     * Para el personal de departamento (puestos 2, 5, 8 y 15 con id_gas = 8) la
     * lista permitida es exclusivamente su departamento, asi que no pueden ver
     * ni finalizar la actividad de ninguna otra localidad escribiendo el id a
     * mano en la peticion.
     */
    private static function resolverLocalidad(int $solicitada, array $permisos = []): int
    {
        if (!$permisos) {
            $permisos = RecibosNominaService::getPermisos();
        }

        $deptoRestringido = $permisos['depto_restringido'] ?? null;

        if ($solicitada <= 0) {
            $ctx = ModuleStationService::getContext(self::MODULE_KEY) ?? [];
            $solicitada = (int)($ctx['id_depto'] ?? $ctx['id_estacion'] ?? 0);
        }

        if ($solicitada <= 0 && $deptoRestringido !== null) {
            return (int)$deptoRestringido;
        }

        if ($solicitada <= 0) {
            $solicitada = (int)($permisos['id_estacion'] ?? 0);
        }

        if ($solicitada <= 0) {
            return 0;
        }

        if ($deptoRestringido !== null && $solicitada !== (int)$deptoRestringido) {
            JsonResponse::forbidden('Solo puedes consultar la informacion de tu departamento');
        }

        if (!RecibosNominaService::esLocalidadPermitida($solicitada)) {
            JsonResponse::forbidden('La estacion o departamento solicitado no esta disponible para tu usuario');
        }

        return $solicitada;
    }

    /**
     * Valida que el registro pertenezca a una localidad que el usuario puede
     * operar en este modulo. Se usa en los endpoints que reciben un id de
     * registro y no la localidad, para que un id adivinado no permita tocar
     * informacion de otra estacion o departamento.
     */
    private static function validarRegistroLocalidad(int $idLocalidad): void
    {
        if (!RecibosNominaService::esLocalidadPermitida($idLocalidad)) {
            JsonResponse::forbidden('El registro pertenece a una estacion o departamento que no esta disponible para tu usuario');
        }
    }

    public function index(int $idYear)
    {
        $yearResult = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = $yearResult['idYear'];

        $permisos = RecibosNominaService::getPermisos();

        // Sin permiso de lectura: redirige a Home incluso por URL directa.
        if (!$permisos['puedeLeer']) {
            header('Location: /home');
            exit;
        }

        $esMultiestacion = $permisos['multiestacion'];
        $deptoRestringido = $permisos['depto_restringido'];

        if ($deptoRestringido !== null) {
            // El personal de departamento entra siempre a su departamento: se
            // fija el contexto aunque el usuario tenga configuracion de
            // multiestacion y se oculta el selector para que no pueda cambiar
            // a una estacion.
            ModuleStationService::setContext(self::MODULE_KEY, null, (int)$deptoRestringido);
            $idEstacion = (int)$deptoRestringido;
        } else {
            $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];
            if (!$esMultiestacion && $permisos['id_estacion'] === 2) {
                $idEstacion = 0;
            }
        }

        $title = 'Recibos de Nómina ' . $idYear;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1, 2024), '');

        // Bloquea el módulo si la estación/departamento asignado no está disponible.
        if (!$this->guardModuleAccess(self::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/2-recursos-humanos/recibos-nomina/index', [
            'title'            => $title,
            'idYear'           => $idYear,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => self::MODULE_KEY,
            // El contador de pendientes del selector se desactiva en este modulo: se pasa
            // un arreglo vacio para que ModuleStationService::render() no
            // imprima el "(N)" junto a cada estacion o departamento.
            'pendientesData'   => [],
            // El personal de departamento no elige estación: el badge del layout
            // muestra su departamento y el selector no se pinta.
            'ocultarSelectorEstacion' => $deptoRestringido !== null,
            'yearMesTemplate'  => '/departamento-operativo/recursos-humanos/recibos-nomina/{year}',
            'puedeLeer'        => $permisos['puedeLeer'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'esMexdesa'      => $permisos['esMexdesa'],
            'esDirector'       => $permisos['es_director'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/recibos-nomina.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/recibos-nomina.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function data(int $idYear)
    {
        try {
            $permisos = RecibosNominaService::getPermisos();
            if (!$permisos['puedeLeer']) {
                JsonResponse::forbidden('No tienes permiso para consultar la información');
            }

            $idEstacion = self::resolverLocalidad(
                (int)Request::input('id_estacion', 0),
                $permisos
            );
            $periodo = (int)Request::input('periodo', 0);

            if (!$idEstacion || !$periodo) {
                return JsonResponse::success('Sin estación o periodo', [
                    'data'    => [],
                    'resumen' => null
                ]);
            }

            $esSemanal = RecibosNominaService::esSemanal($idEstacion);
            $nominaInfo = RecibosNominaService::getNominaData($idEstacion, $idYear, $periodo, $esSemanal);

            return JsonResponse::success('OK', [
                'data'      => $nominaInfo['rows'],
                'resumen'   => $nominaInfo,
                'esSemanal' => $esSemanal
            ]);
        } catch (\Throwable $e) {
            error_log('Error DataTable recibos nomina: ' . $e->getMessage()
                . ' | idEstacion=' . $idEstacion . ' year=' . $idYear . ' periodo=' . $periodo
                . ' in ' . $e->getFile() . ':' . $e->getLine());

            return JsonResponse::custom([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => []
            ], 200);
        }
    }

    public function getComentarios(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso para consultar la información');
        }

        $idReporte = (int)Request::input('idReporte', 0);

        $registro = OpReciboNominaV2::find($idReporte);
        if (!$registro) {
            JsonResponse::notFound('Registro no encontrado');
        }

        self::validarRegistroLocalidad((int)$registro->id_estacion);

        $sessionUsuario = Session::get('usuario') ?? [];
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        $comentarios = OpReciboNominaV2Comentario::with('usuario')
            ->where('id_nomina', $idReporte)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($c) use ($idUsuario) {
                return [
                    'id'             => $c->id,
                    'nombre_usuario' => $c->usuario?->nombre ?? 'Usuario',
                    'comentario'     => $c->comentario,
                    'fecha_hora' => $c->fecha_hora ? formatearFecha($c->fecha_hora) . ', ' . date('g:i a', strtotime($c->fecha_hora)) : '', 
                    'es_propio'      => (int)($c->id_usuario ?? 0) === $idUsuario
                ];
            })
            ->values();



        JsonResponse::success('OK', ['data' => $comentarios->toArray()]);
    }

    public function guardarComentario(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso para consultar la información');
        }

        $idReporte = (int)Request::input('idReporte', 0);
        $comentario = trim((string)Request::input('comentario', ''));
        $sessionUsuario = Session::get('usuario') ?? [];

        $registro = OpReciboNominaV2::find($idReporte);
        if (!$registro) {
            JsonResponse::notFound('Registro no encontrado');
        }

        self::validarRegistroLocalidad((int)$registro->id_estacion);

        if (!$idReporte || empty($comentario)) {
            JsonResponse::error('Datos incompletos');
        }

        OpReciboNominaV2Comentario::create([
            'id_nomina'   => $idReporte,
            'id_usuario'  => (int)($sessionUsuario['id'] ?? 0),
            'comentario'  => $comentario,
            'fecha_hora'  => date('Y-m-d H:i:s')
        ]);

        JsonResponse::success('Comentario guardado exitosamente');
    }

    public function guardarEdicion(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar registros');
        }

        $idReporte = (int)Request::input('idReporte', 0);

        $registro = OpReciboNominaV2::find($idReporte);
        if (!$registro) {
            JsonResponse::notFound('Registro no encontrado');
        }

        // El id del registro no basta: tambien se valida la localidad a la que
        // pertenece para que nadie edite informacion de otra estacion.
        self::validarRegistroLocalidad((int)$registro->id_estacion);

        // Solo se actualiza lo que llega en el payload: la UI ya aplicó la
        // matriz de permisos del legacy y omite lo que el rol no puede tocar.
        if (Request::has('importe')) {
            $registro->importe_total = (float)Request::input('importe', 0);
        }
        if (Request::has('original')) {
            $registro->nomina_original = (int)Request::input('original', 0);
        }
        if (Request::has('prima_vacacional')) {
            $registro->prima_vacacional = (int)Request::input('prima_vacacional', 0);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/archivos/recibos-nomina-v2/';

        if (!empty($_FILES['doc_nomina']) && $_FILES['doc_nomina']['error'] === UPLOAD_ERR_OK) {
            $name = self::guardarArchivo($_FILES['doc_nomina'], $uploadDir, 'acuses', 'AcuseNomina');
            if ($name !== null && !is_dir($uploadDir . 'acuses/')) mkdir($uploadDir . 'acuses/', 0777, true);
            if ($name !== null) $registro->doc_nomina = $name;
        }

        if (!empty($_FILES['doc_nomina_firma']) && $_FILES['doc_nomina_firma']['error'] === UPLOAD_ERR_OK) {
            $name = self::guardarArchivo($_FILES['doc_nomina_firma'], $uploadDir, 'firmados', 'FirmaNomina');
            if ($name !== null && !is_dir($uploadDir . 'firmados/')) mkdir($uploadDir . 'firmados/', 0777, true);
            if ($name !== null) $registro->doc_nomina_firma = $name;
        }

        if (!empty($_FILES['doc_nomina_aguinaldo']) && $_FILES['doc_nomina_aguinaldo']['error'] === UPLOAD_ERR_OK) {
            $name = self::guardarArchivo($_FILES['doc_nomina_aguinaldo'], $uploadDir, 'aguinaldo', 'Aguinaldo');
            if ($name !== null && !is_dir($uploadDir . 'aguinaldo/')) mkdir($uploadDir . 'aguinaldo/', 0777, true);
            if ($name !== null) $registro->doc_nomina_aguinaldo = $name;
        }

        $registro->save();
        JsonResponse::success('Registro editado exitosamente');
    }

    public function eliminarUsuario(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar registros');
        }

        // El DELETE GLOBAL de Portal3 (actions.alpine.js) siempre envia "id".
        // Se acepta tambien "idReporte" por compatibilidad con llamadas previas.
        $idReporte = (int)(Request::input('id', 0) ?: Request::input('idReporte', 0));
        if (!$idReporte) {
            JsonResponse::error('ID inválido');
        }

        $registro = OpReciboNominaV2::find($idReporte);
        if (!$registro) {
            JsonResponse::notFound('Registro no encontrado');
        }

        self::validarRegistroLocalidad((int)$registro->id_estacion);

        OpReciboNominaV2::where('id', $idReporte)->delete();
        OpReciboNominaV2Comentario::where('id_nomina', $idReporte)->delete();

        JsonResponse::success('Usuario eliminado exitosamente');
    }

    public function finalizarActividad(): void
    {
        $permisos = RecibosNominaService::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar la actividad');
        }

        // El id de la estacion/departamento llega del navegador. Antes de
        // tocar nada se valida que sea una localidad que el usuario tenga
        // asignada: es lo que impide que un usuario finalice la actividad de
        // una estacion que no le pertenece. Para el personal de departamento
        // la validacion es mas fuerte todavia, solo se admite su departamento.
        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0), $permisos);

        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');
        $idResponsable = (int)Request::input('idResponsable', 2);

        if (!$idEstacion || !$year || !$periodo) {
            JsonResponse::error('Datos incompletos');
        }

        // Paridad con el legacy: la autorización de rol vive en la UI (botones
        // visibles por rol). Aqui solo se validan las precondiciones de negocio
        // que el legacy comprobaba antes de insertar el puntaje.
        $nomina = RecibosNominaService::getNominaData($idEstacion, $year, $periodo, $descripcion === 'Semana');

        if ($idResponsable === 1 && empty($nomina['doc_nomina_acuse'])) {
            JsonResponse::error('No has subido los recibos de nómina del personal.');
        }

        if ($idResponsable === 2) {
            // "Recibos Estacion" solo lo cierra la estación o el departamento al
            // que pertenece el usuario. Un usuario multiestacion puede consultar
            // el detalle de otras estaciones, pero no cerrarlas.
            if (!RecibosNominaService::esLocalidadPropia($idEstacion)) {
                JsonResponse::forbidden('La actividad solo puede ser finalizada por la estación o departamento al que perteneces');
            }

            if (!$nomina['puede_finalizar_estacion']) {
                JsonResponse::error('No es posible finalizar la actividad, se debe de agregar toda la información.');
            }
        }

        if ($idResponsable === 3 && !($nomina['finalizado_estacion'] && !$nomina['finalizado_operativo'])) {
            JsonResponse::error('La estación no ha finalizado su actividad.');
        }

        $resultado = RecibosNominaService::finalizarActividad($idEstacion, $year, $periodo, $descripcion, $idResponsable);

        if (!$resultado['success']) {
            JsonResponse::error($resultado['message']);
        }

        JsonResponse::success($resultado['message']);
    }

    public function periodos(int $idYear)
    {
        try {
            $permisos = RecibosNominaService::getPermisos();
            if (!$permisos['puedeLeer']) {
                JsonResponse::forbidden('No tienes permiso para consultar la información');
            }

            $idEstacion = self::resolverLocalidad(
                (int)Request::input('id_estacion', 0),
                $permisos
            );

            if (!$idEstacion) {
                return JsonResponse::success('Sin estación', ['es_semanal' => true, 'periodos' => [], 'periodo_actual' => 1]);
            }

            return JsonResponse::success('OK', RecibosNominaService::getPeriodosAnio($idEstacion, $idYear));
        } catch (\Throwable $e) {
            return JsonResponse::custom([
                'success' => false,
                'message' => 'Error al consultar los periodos',
            ]);
        }
    }

    public function personalFaltantes(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso para consultar la información');
        }

        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');

        $list = RecibosNominaService::listarPersonalFaltante($idEstacion, $year, $periodo, $descripcion === 'Semana');

        JsonResponse::success('OK', ['data' => $list]);
    }

    public function guardarPersonal(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para agregar personal');
        }

        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');
        $personal = (array)Request::input('personal', []);

        if (!$idEstacion || !$year || !$periodo || empty($personal)) {
            JsonResponse::error('Datos incompletos');
        }

        // Validacion del legacy: el personal solo se captura en periodos que
        // ya vencieron. Sin esto se podrian agregar registros en semanas o
        // quincenas futuras.
        if (!RecibosNominaService::periodoAceptaInsercion($year, $periodo, $descripcion === 'Semana')) {
            JsonResponse::error(
                'No es posible agregar personal: la '
                . ($descripcion === 'Semana' ? 'semana' : 'quincena')
                . ' ' . $periodo . ' del ' . $year . ' aún no se ha liquidado.'
            );
        }

        RecibosNominaService::agregarPersonal($idEstacion, $year, $periodo, $descripcion, $personal);
        JsonResponse::success('Personal agregado exitosamente');
    }

    public function getAcuses(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso para consultar la información');
        }

        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');

        $list = RecibosNominaService::getAcusesPeriodo($idEstacion, $year, $periodo, $descripcion);

        JsonResponse::success('OK', ['data' => $list]);
    }

    public function guardarAcuse(): void
    {
        $permisos = RecibosNominaService::getPermisos();
        if (!$permisos['puedeCrear'] && !$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para subir documentos');
        }

        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::error('Error al subir el archivo');
        }

        $name = self::guardarArchivoPeriodoAcuse($_FILES['archivo']);
        if ($name === null) {
            JsonResponse::error('Error al subir el archivo');
        }

        RecibosNominaService::guardarAcusePeriodo($idEstacion, $year, $periodo, $descripcion, $name);
        JsonResponse::success('Documento agregado exitosamente');
    }

    public function eliminarAcuse(): void
    {
        if (!RecibosNominaService::getPermisos()['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar documentos');
        }

        $id = (int)Request::input('id', 0);
        if (!$id) {
            JsonResponse::error('ID inválido');
        }

        $acuse = OpReciboNominaPeriodoAcuse::find($id);
        if (!$acuse) {
            JsonResponse::notFound('Documento no encontrado');
        }

        self::validarRegistroLocalidad((int)$acuse->id_estacion);

        RecibosNominaService::eliminarAcusePeriodo($id);
        JsonResponse::success('Documento eliminado exitosamente');
    }

    public function subirAcuseMexdesa(): void
    {
        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');
        $idAcuse = (int)Request::input('idAcuse', 0);

        if (empty($_FILES['doc_nomina_acuse']) || $_FILES['doc_nomina_acuse']['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::error('Selecciona un archivo para subir');
        }

        $uploadDir = __DIR__ . '/../../public/uploads/archivos/recibos-nomina-v2/';
        $name = self::guardarArchivo($_FILES['doc_nomina_acuse'], $uploadDir, 'recibos-mexdesa', 'AcuseMexdesa');
        if ($name === null) {
            JsonResponse::error('Error al subir el archivo');
        }

        RecibosNominaService::guardarAcuseMexdesa($idEstacion, $year, $periodo, $descripcion, $name, $idAcuse);
        JsonResponse::success('Recibos de nómina subidos exitosamente');
    }

    public function subirAguinaldo(): void
    {
        $idEstacion = self::resolverLocalidad((int)Request::input('idEstacion', 0));
        $year = (int)Request::input('year', 0);
        $periodo = (int)Request::input('periodo', 0);
        $descripcion = (string)Request::input('descripcion', 'Semana');
        $idAguinaldo = (int)Request::input('idAguinaldo', 0);

        if (empty($_FILES['doc_aguinaldo']) || $_FILES['doc_aguinaldo']['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::error('Selecciona un archivo para subir');
        }

        $uploadDir = __DIR__ . '/../../public/uploads/archivos/recibos-nomina-v2/';
        $name = self::guardarArchivo($_FILES['doc_aguinaldo'], $uploadDir, 'recibos-mexdesa', 'Acuses-Aguinaldo');
        if ($name === null) {
            JsonResponse::error('Error al subir el archivo');
        }

        RecibosNominaService::subirAguinaldo($idEstacion, $year, $periodo, $descripcion, $name, $idAguinaldo);
        JsonResponse::success('Recibos de aguinaldo subidos exitosamente');
    }

    public function finalizarAguinaldo(): void
    {
        $idAguinaldo = (int)Request::input('idAguinaldo', 0);
        if (!$idAguinaldo) {
            JsonResponse::error('ID inválido');
        }

        $aguinaldo = OpReciboNominaAguinaldo::find($idAguinaldo);
        if (!$aguinaldo) {
            JsonResponse::notFound('Registro no encontrado');
        }

        self::validarRegistroLocalidad((int)$aguinaldo->id_estacion);

        RecibosNominaService::finalizarAguinaldo($idAguinaldo);
        JsonResponse::success('Actividad finalizada exitosamente');
    }

public function revision(int $idYear, int $idMes)
    {
        $idYear = (int)DropdownYearMesService::validarYearMes($idYear, null)['idYear'];
        $mes    = self::validarMesRevision((int)$idMes);

        $permisos = RecibosNominaService::getPermisos();
        if (!$permisos['es_director']) {
            header('Location: /home');
            exit;
        }

        $title = "Revisión Recibos de Nómina (" . nombremes($mes) . " $idYear)";

        // La estacion se toma del contexto del selector compartido, igual que
        // en el index y en los KPI's. Antes se resolvia con un select propio
        // que listaba todas las localidades y caia en la primera por defecto,
        // con lo que se podian ver datos de una localidad no autorizada.
        $ctx = ModuleStationService::getContext(self::MODULE_KEY) ?? [];
        $idEstacion = (int)($ctx['id_depto'] ?? $ctx['id_estacion'] ?? 0);

        // Solo se aceptan las localidades que el modulo tiene asignadas.
        if ($idEstacion && !RecibosNominaService::esLocalidadPermitida($idEstacion)) {
            $idEstacion = 0;
        }

        $data = RecibosNominaService::dataRevision($idEstacion, $idYear, $mes);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Recibos de Nómina', '/departamento-operativo/recursos-humanos/recibos-nomina/' . $idYear);
        Breadcrumb::add('Revisión', '');
        Breadcrumb::add(self::dropdownMesRevision($idYear, $mes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $mes, 2024), '');

        // Bloquea el modulo si la estacion/departamento asignado no esta disponible.
        if (!$this->guardModuleAccess(self::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/2-recursos-humanos/recibos-nomina/revision', [
            'title'           => $title,
            'idYear'          => $idYear,
            'esDirector'      => $permisos['es_director'],
            'puedeEditar'     => $permisos['puedeEditar'],
            'idEstacion'      => $idEstacion,
            'mes'             => $mes,
            'revisionData'    => $data,
            'multiestacion'   => $permisos['multiestacion'],
            'moduleStationKey' => self::MODULE_KEY,
            // Sin contador de pendientes en el selector (ver index()).
            'pendientesData'  => [],
            // El año y el mes van ambos en la ruta, igual que en Evaluación.
            'yearMesTemplate' => '/departamento-operativo/recursos-humanos/recibos-nomina-revision/{year}/{mes}',
            'help'            => false,
            'scripts' => [
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/recibos-nomina.revision.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function excelDespachadores(): void
    {
        $permisos = RecibosNominaService::getPermisos();
        if (!$permisos['es_director']) {
            header('Location: /home');
            exit;
        }

        $idEstacion = (int)Request::input('idEstacion', 0);
        $year = (int)Request::input('year', 0);
        $mes = (int)Request::input('mes', 0);

        if (!$idEstacion || !$year || $mes < 1 || $mes > 12) {
            header('Location: /home');
            exit;
        }

        // La estación viene por query string, así que se valida contra las
        // localidades que el módulo tiene asignadas. Sin esto se podría bajar el
        // Excel de cualquier estación escribiendo el id en la URL.
        if (!RecibosNominaService::esLocalidadPermitida($idEstacion)) {
            JsonResponse::forbidden('La estación solicitada no está disponible para este módulo');
        }

        $localidad = RhLocalidad::find($idEstacion);
        $nombreES = $localidad?->localidad ?? '';
        $nombreMes = nombremes($mes);

        $csv = RecibosNominaService::getExcelDespachadores($idEstacion, $year, $mes);

        header('Content-Encoding: UTF-8');
        header('Content-Type: text/csv; charset=ISO-8859-1');
        header('Content-Disposition: attachment; filename="Importe de Nomina ' . $nombreES . ' ' . $nombreMes . ' ' . $year . '.csv"');
        echo $csv;
        exit;
    }

    public function evaluacion(int $idYear, int $idMes)
    {
        $permisos = RecibosNominaService::getPermisos();
        if (!$permisos['puedeLeer']) {
            header('Location: /home');
            exit;
        }

        $idYear = (int)DropdownYearMesService::validarYearMes($idYear, null)['idYear'];
        $idMes  = self::validarMesEvaluacion($idMes);

        $moduleCtx = ModuleStationService::getContext(self::MODULE_KEY);
        // Los departamentos viven en la clave id_depto del contexto, por eso se
        // resuelve con el mismo criterio que el resto del modulo.
        $idEstacion = (int)($moduleCtx['id_depto'] ?? $moduleCtx['id_estacion'] ?? 0);
        if ($idEstacion && !RecibosNominaService::esLocalidadPermitida($idEstacion)) {
            $idEstacion = 0;
        }

        $title = "Evaluación Recibos de Nómina (" . nombremes($idMes) . " " . $idYear . ")";

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Recibos de Nómina', '/departamento-operativo/recursos-humanos/recibos-nomina/' . $idYear);
        Breadcrumb::add($title, '');
        Breadcrumb::add(self::dropdownMesEvaluacion($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1, 2024), '');

        // Bloquea el módulo si la estación/departamento asignado no está disponible.
        if (!$this->guardModuleAccess(self::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/2-recursos-humanos/recibos-nomina/evaluacion', [
            'title'            => $title,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $permisos['multiestacion'],
            'moduleStationKey' => self::MODULE_KEY,
            'ocultarSelectorEstacion' => $permisos['depto_restringido'] !== null,
            'yearMesTemplate'  => '/departamento-operativo/recursos-humanos/recibos-nomina-evaluacion/{year}/{mes}',
            'help'             => false,
            'scripts' => [
                '/assets/libs/apexcharts/dist/apexcharts.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/recibos-nomina-evaluacion.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function evaluacionData(int $idYear, int $idMes)
    {
        $permisos = RecibosNominaService::getPermisos();
        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso para consultar la información');
        }

        $idYear = (int)DropdownYearMesService::validarYearMes($idYear, null)['idYear'];
        $idMes  = self::validarMesEvaluacion($idMes);

        $moduleCtx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = (int)($moduleCtx['id_depto'] ?? $moduleCtx['id_estacion'] ?? 0);

        if (!$idEstacion || !RecibosNominaService::esLocalidadPermitida($idEstacion)) {
            JsonResponse::error('Selecciona una estación');
        }

        try {
            $data = KpiRecibosNominaService::getData($idEstacion, $idYear, $idMes);
        } catch (\Throwable $e) {
            error_log('Error KPI recibos nomina: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            JsonResponse::custom([
                'success' => false,
                'type'    => JsonResponse::TYPE_ERROR,
                'message' => 'No se pudo cargar la evaluación'
            ], 500);
        }

        JsonResponse::success('OK', $data);
    }

    private static function validarMesEvaluacion(int $idMes): int
    {
        if ($idMes < 1 || $idMes > 13) {
            return (int)date('n');
        }
        return $idMes;
    }

    /**
     * Dropdown de meses de la revisión: 1-12, sin "Anual" porque la revisión
     * siempre trabaja un mes a la vez. En el año en curso se ocultan los meses
     * que todavía no han llegado.
     */
    private static function dropdownMesRevision(int $idYear, int $idMes): string
    {
        $mesActual = (int)date('n');

        $html = '
    <a class="dropdown-toggle breadcrumb-item active pointer" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ti ti-calendar-month"></i> <span class="ms-1">' . nombremes($idMes) . '</span>
    </a>

    <ul class="dropdown-menu">';

        for ($i = 12; $i >= 1; $i--) {
            $clase = ($idYear >= (int)date('Y') && $i > $mesActual) ? 'd-none' : '';
            $html .= '
        <li class="' . $clase . '">
            <a class="dropdown-item pointer' . ($i === $idMes ? ' active' : '') . '" x-on:click.prevent="cambiarYearMes(' . $idYear . ',' . $i . ')">
            <i class="ti ti-calendar-month"></i> <span class="ms-1">' . nombremes($i) . '</span>
            </a>
        </li>';
        }

        $html .= '
        </ul>';

        return $html;
    }

    /**
     * Mes de la revisión: 1-12. Si no llega uno válido se usa el mes en curso.
     */
    private static function validarMesRevision(int $idMes): int
    {
        if ($idMes < 1 || $idMes > 12) {
            return (int)date('n');
        }
        return $idMes;
    }

    /**
     * Dropdown de meses de la evaluación: 1-12 más "Anual" (13).
     */
    private static function dropdownMesEvaluacion(int $idYear, int $idMes): string
    {
        $html = '
    <a class="dropdown-toggle breadcrumb-item active pointer" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ti ti-calendar-stats"></i> <span class="ms-1">' . ($idMes === 13 ? 'Anual' : nombremes($idMes)) . '</span>
    </a>

    <ul class="dropdown-menu">';

        for ($i = 12; $i >= 1; $i--) {
            $clase = ($idYear >= date('Y') && $i > (int)date('n')) ? 'd-none' : '';
            $html .= '
        <li class="' . $clase . '">
            <a class="dropdown-item pointer" x-on:click.prevent="cambiarYearMes(' . $idYear . ',' . $i . ')">
            <i class="ti ti-calendar-stats"></i> <span class="ms-1">' . nombremes($i) . '</span>
            </a>
        </li>';
        }

        $html .= '
        <li>
            <a class="dropdown-item pointer" x-on:click.prevent="cambiarYearMes(' . $idYear . ',13)">
            <i class="ti ti-calendar-stats"></i> <span class="ms-1">Anual</span>
            </a>
        </li>';

        $html .= '</ul>';

        return $html;
    }

    private static function guardarArchivoPeriodoAcuse(array $file): ?string
    {
        $dir = __DIR__ . '/../../public/uploads/archivos/recibo-nomina-acuse/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $original = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $name = uniqid() . '-' . $original;

        return move_uploaded_file($file['tmp_name'], $dir . $name)
            ? $name
            : null;
    }

    private static function guardarArchivo(array $file, string $uploadDir, string $carpeta, string $prefijo): ?string
    {
        if (!is_dir($uploadDir . $carpeta)) {
            mkdir($uploadDir . $carpeta, 0777, true);
        }

        $original = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $name = uniqid() . '-' . $prefijo . '-' . $original;

        return move_uploaded_file($file['tmp_name'], $uploadDir . $carpeta . '/' . $name)
            ? $name
            : null;
    }
}