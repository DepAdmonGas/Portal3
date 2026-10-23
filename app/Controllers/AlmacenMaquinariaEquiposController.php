<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenMaquinariaEquiposService as Service;
use App\Services\ModuleStationService;

/**
 * Maquinaria y Equipos — Almacén
 *
 * Migración del module legacy (departamento-operativo) `.../maquinaria-equipos/*.php`
 * sobre op_maquinaria_equipos + op_maquinaria_equipos_comentario.
 *
 * El listado resuelve en el backend la estación/departamento según el contexto del
 * módulo (ModuleStationService), igual que los demás módulos de almacén: jamás se
 * filtra por el id enviado por el cliente sin antes validarlo contra las localidades
 * autorizadas del usuario.
 */
class AlmacenMaquinariaEquiposController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/maquinaria-equipos';
    private const TITULO   = 'Maquinaria y Equipos';

    public function index(): void
    {
        $title = self::TITULO;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = Service::getPermisos();

        // El legacy armaba el breadcrumb según el puesto: Encargado y Asistente
        // Administrativo llegaban desde Almacén; el resto, desde Mantenimiento.
        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = ($contexto['id_estacion'] ?? null) !== null
            ? (int)$contexto['id_estacion']
            : ($contexto['id_depto'] ?? null);

        View::render('departamento-operativo/4-almacen/maquinaria-equipos/index', [
            'title'             => $title,
            'moduleStationKey'  => Service::MODULE_KEY,
            'estacionFija'      => $seleccion !== null,
            'estacionActual'    => (int)($seleccion ?? 0),
            'esUsuarioEstacion' => $permisos['esUsuarioEstacion'],
            'maquinariaOpciones'    => Service::MAQUINARIA_OPCIONES,
            'maquinariaOpcionesJson'=> json_encode(Service::MAQUINARIA_OPCIONES, JSON_UNESCAPED_UNICODE),
            'statusLabels'      => Service::STATUS_LABELS,
            'puedeCrear'        => $permisos['puedeCrear'],
            'puedeEditar'       => $permisos['puedeEditar'],
            'puedeEliminar'     => $permisos['puedeEliminar'],
            'puedeDescargar'    => $permisos['puedeDescargar'],
            'scripts'           => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/libs/select2/dist/js/select2.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/maquinaria-equipos.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/maquinaria-equipos.actions.init.js?v=' . time(),
            ],
            'links'             => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function data(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getData(),
        ]);
    }

    public function store(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar maquinaria.');
        }

        $result = Service::store($_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function detalle(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id      = (int)(Request::input('id') ?? 0);
        $detalle = Service::getDetalle($id);

        if ($detalle === null) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        JsonResponse::custom(['success' => true, 'data' => $detalle]);
    }

    public function update(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar maquinaria.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::update($id, $_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para dar de baja maquinaria.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::destroy($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function comentarios(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        JsonResponse::custom([
            'success'    => true,
            'comentarios'=> Service::getComentarios($id),
        ]);
    }

    public function comentarioStore(): void
    {
        // El legacy permitía comentar a todo el que tuviera acceso de lectura.
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::guardarComentario($id, Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}