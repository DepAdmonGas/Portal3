<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\ModuleStationService;
use App\Services\PivoteoService;

class PivoteoController extends BaseController
{
    public function index()
    {
        $permisos = PivoteoService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(PivoteoService::MODULE_KEY, 'Pivoteo', 'departamento-operativo')) {
            return;
        }

        $title = 'Pivoteo';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/pivoteo/index', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idPuesto'         => $permisos['id_puesto'],
            'idEstacion'       => PivoteoService::getEstacionSeleccionada(),
            'multiestacion'    => $permisos['multiestacion'],
            'permisos'         => $permisos,
            'moduleStationKey' => PivoteoService::MODULE_KEY,
            'pendientesData'   => PivoteoService::getPendientes(),
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/pivoteo.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/pivoteo.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        $input = Request::all();
        $idEstacion = isset($input['id_estacion']) && $input['id_estacion'] !== ''
            ? (int)$input['id_estacion']
            : PivoteoService::getEstacionSeleccionada();

        JsonResponse::success('OK', [
            'data'     => PivoteoService::getListado($idEstacion),
            'permisos' => $permisos,
        ]);
    }

    public function getEditar(int $id)
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeDetalle']) {
            JsonResponse::forbidden('No tienes acceso a este registro.');
        }

        try {
            $datos = PivoteoService::getEditar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('OK', ['data' => $datos]);
    }

    public function crear()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permisos para crear registros.');
        }

        $idEstacion = PivoteoService::getEstacionSeleccionada();
        if ($idEstacion === null) {
            JsonResponse::error('Selecciona una estación para registrar el pivoteo.');
        }

        if (!PivoteoService::estacionAutorizada($idEstacion)) {
            JsonResponse::error('No tienes acceso a la estación seleccionada.');
        }

        try {
            $id = PivoteoService::crear($idEstacion);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo creado exitosamente.', ['id' => $id]);
    }

    public function agregarDetalle()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para agregar líneas.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID de pivoteo inválido.');
        }

        $datos = $this->extraerDatosDetalle($input);

        try {
            $idDetalle = PivoteoService::agregarDetalle($id, $datos);
            $fila = PivoteoService::detalleFila($idDetalle);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo agregado exitosamente.', ['fila' => $fila]);
    }

    public function agregarDetalleVacio()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para agregar líneas.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID de pivoteo inválido.');
        }

        try {
            $idDetalle = PivoteoService::agregarDetalleVacio($id);
            $fila = PivoteoService::detalleFila($idDetalle);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo agregado exitosamente.', ['fila' => $fila]);
    }

    public function editarDetalle()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para editar líneas.');
        }

        $input = Request::all();
        $idDetalle = (int)($input['id_detalle'] ?? 0);
        $opcion = (int)($input['opcion'] ?? 0);
        $valor = trim((string)($input['valor'] ?? ''));

        if ($idDetalle <= 0 || $opcion <= 0) {
            JsonResponse::error('Parámetros inválidos.');
        }

        try {
            PivoteoService::editarDetalle($idDetalle, $opcion, $valor);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        $respuesta = [];
        if (in_array($opcion, [1, 2, 3, 4, 5, 10, 11, 12], true)) {
            $respuesta['fila'] = PivoteoService::detalleFila($idDetalle);
        }

        JsonResponse::success('Pivoteo actualizada exitosamente.', $respuesta);
    }

    public function editarDetalleEstacion()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para editar líneas.');
        }

        $input = Request::all();
        $idDetalle = (int)($input['id_detalle'] ?? 0);
        $categoria = (int)($input['categoria'] ?? 0);
        $estacion = trim((string)($input['estacion'] ?? ''));
        $destino = trim((string)($input['destino'] ?? ''));

        if ($idDetalle <= 0 || !in_array($categoria, [1, 2], true) || $estacion === '') {
            JsonResponse::error('Parámetros inválidos.');
        }

        try {
            PivoteoService::editarEstacion($idDetalle, $categoria, $estacion, $destino);
            $fila = PivoteoService::detalleFila($idDetalle);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Estación actualizada exitosamente.', ['fila' => $fila]);
    }

    public function eliminarDetalle()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar líneas.');
        }

        $input = Request::all();
        $idDetalle = (int)($input['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            JsonResponse::error('ID de Pivoteo inválido.');
        }

        try {
            PivoteoService::eliminarDetalle($idDetalle);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo eliminada.', ['id_detalle' => $idDetalle]);
    }

    public function finalizar()
    {
        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        try {
            PivoteoService::finalizar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo finalizado exitosamente.');
    }

    public function eliminar()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar registros.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        try {
            PivoteoService::eliminar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Pivoteo eliminado.');
    }

    public function generarToken()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeFirmar']) {
            JsonResponse::forbidden('No tienes permisos para generar tokens de firma.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $via = (string)($input['via'] ?? 'telegram');
        if (!in_array($via, ['telegram', 'email'], true)) {
            JsonResponse::error('La vía de envío no es válida.');
        }
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $resultado = PivoteoService::crearToken($id, $via);
        if (!($resultado['success'] ?? false)) {
            JsonResponse::error($resultado['message'] ?? 'No se pudo generar el token.');
        }

        JsonResponse::success($resultado['message'] ?? 'Token generado exitosamente.');
    }

    public function firmar()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeFirmar']) {
            JsonResponse::forbidden('No tienes permisos para firmar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $tipoFirma = strtoupper(trim((string)($input['tipo_firma'] ?? '')));
        $token = (int)($input['token'] ?? 0);

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }
        if (!in_array($tipoFirma, ['A', 'B', 'C'], true)) {
            JsonResponse::error('El tipo de firma no es válido.');
        }
        if ($token <= 0) {
            JsonResponse::error('El token no es válido.');
        }

        $resultado = PivoteoService::firmar($id, $tipoFirma, $token);
        if (!($resultado['success'] ?? false)) {
            JsonResponse::error($resultado['message'] ?? 'No se pudo firmar el pivoteo.');
        }

        JsonResponse::success($resultado['message'] ?? 'Pivoteo firmado exitosamente.');
    }

    public function enviarCorreo()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEnviarCorreo']) {
            JsonResponse::forbidden('No tienes permisos para enviar el pivoteo por correo.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $correo = trim((string)($input['correo'] ?? ''));
        $asunto = trim((string)($input['asunto'] ?? ''));
        $contenido = trim((string)($input['contenido'] ?? ''));

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $resultado = PivoteoService::enviarCorreo($id, $correo, $asunto, $contenido);
        if (!($resultado['success'] ?? false)) {
            JsonResponse::error($resultado['message'] ?? 'No se pudo enviar el correo.');
        }

        JsonResponse::success($resultado['message'] ?? 'Correo enviado exitosamente.', [
            'historial' => PivoteoService::getHistorialCorreos($id),
        ]);
    }

    public function pdf(int $id)
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeGenerarPDF']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        try {
            $binario = PivoteoService::generarPdf($id);
        } catch (\Throwable $e) {
            JsonResponse::notFound($e->getMessage());
        }

        $nombre = 'Pivoteo PDF-' . $id . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($binario));
        echo $binario;
        exit;
    }

    public function editar(int $id)
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(PivoteoService::MODULE_KEY, 'Pivoteo', 'departamento-operativo')) {
            return;
        }

        try {
            $datos = PivoteoService::getEditar($id);
        } catch (\Throwable $e) {
            View::render('errors/404', ['message' => $e->getMessage()], 'departamento-operativo');
            return;
        }

        $contextoCtx = Session::get('module_context') ?? [];
        $contextoAnterior = $contextoCtx[PivoteoService::MODULE_KEY] ?? null;

        ModuleStationService::setContext(PivoteoService::MODULE_KEY, $datos['id_estacion']);

        $title = 'Formulario Pivoteo (#' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Pivoteo', '/departamento-operativo/importacion/pivoteo');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/pivoteo/editar', [
            'title'                   => $title,
            'detalle'                 => $datos,
            'moduleStationKey'        => PivoteoService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'pendientesData'          => PivoteoService::getPendientes(),
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/pivoteo.editar.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');

        $ctxRestaurado = Session::get('module_context') ?? [];
        if ($contextoAnterior === null) {
            unset($ctxRestaurado[PivoteoService::MODULE_KEY]);
        } else {
            $ctxRestaurado[PivoteoService::MODULE_KEY] = $contextoAnterior;
        }
        Session::set('module_context', $ctxRestaurado);
    }

    public function firmarPage(int $id)
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(PivoteoService::MODULE_KEY, 'Pivoteo', 'departamento-operativo')) {
            return;
        }

        try {
            $datos = PivoteoService::getEditar($id);
        } catch (\Throwable $e) {
            View::render('errors/404', ['message' => $e->getMessage()], 'departamento-operativo');
            return;
        }

        $contextoCtx = Session::get('module_context') ?? [];
        $contextoAnterior = $contextoCtx[PivoteoService::MODULE_KEY] ?? null;

        ModuleStationService::setContext(PivoteoService::MODULE_KEY, $datos['id_estacion']);

        $title = 'Firmar Pivoteo (#' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Pivoteo', '/departamento-operativo/importacion/pivoteo');
        Breadcrumb::add($title, '');

        $detalle = array_merge($datos, [
            'puede_firmar_ahora' => $permisos['puedeFirmar'] && (int)$datos['estatus'] === PivoteoService::ESTATUS_FINALIZADO,
        ]);

        View::render('departamento-operativo/3-importacion/pivoteo/firmar', [
            'title'                   => $title,
            'detalle'                 => $detalle,
            'moduleStationKey'        => PivoteoService::MODULE_KEY,
            'pendientesData'          => PivoteoService::getPendientes(),
         'ocultarSelectorEstacion'=> true,
            'links' => [],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/pivoteo.firmar.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');

        $ctxRestaurado = Session::get('module_context') ?? [];
        if ($contextoAnterior === null) {
            unset($ctxRestaurado[PivoteoService::MODULE_KEY]);
        } else {
            $ctxRestaurado[PivoteoService::MODULE_KEY] = $contextoAnterior;
        }
        Session::set('module_context', $ctxRestaurado);
    }

    public function editarFecha()
    {
        $permisos = PivoteoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para cambiar la fecha.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $fecha = trim((string)($input['fecha'] ?? ''));

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }
        if ($fecha === '' || !PivoteoService::fechaValida($fecha)) {
            JsonResponse::error('Fecha inválida.');
        }

        try {
            PivoteoService::editarFecha($id, $fecha);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Fecha actualizada exitosamente.');
    }

    private function extraerDatosDetalle(array $input): array
    {
        $claves = ['producto', 'tanque', 'litros', 'tad', 'unidad', 'chofer'];

        $datos = [];
        foreach ($claves as $clave) {
            $datos[$clave] = $input[$clave] ?? '';
        }

        return $datos;
    }
}