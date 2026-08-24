<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\RolComodinesService;
use Dompdf\Dompdf;
use Dompdf\Options;

class RolComodinesController extends BaseController
{
    public function index()
    {
        $permisos = RolComodinesService::getPermisos();

        $title = 'Rol de Comodines';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Incidencias de Nómina', '/departamento-operativo/recursos-humanos/incidencias-nomina/' . date('Y'));
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/rol-comodines/index', [
            'title'            => $title,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/rol-comodines.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/rol-comodines.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function form(int $idReporte)
    {
        $idReporte = (int)$idReporte;
        if ($idReporte <= 0) {
            http_response_code(404);
            echo 'ID no válido';
            exit;
        }

        $detail = RolComodinesService::getDetail($idReporte);
        if (!$detail) {
            http_response_code(404);
            echo 'Registro no encontrado';
            exit;
        }

        $permisos = RolComodinesService::getPermisos();
 
        //$title = 'Rol de Comodines (# 00' . $idReporte . ')';
        $title = $detail['status'] == 1 ? 'Detalle' : 'Formulario (#00' . $idReporte . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Incidencias de Nómina', '/departamento-operativo/recursos-humanos/incidencias-nomina/' . date('Y'));
        Breadcrumb::add('Rol de Comodines', '/departamento-operativo/recursos-humanos/rol-comodines');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/rol-comodines/form', [
            'title'           => $title,
            'detail'          => $detail,
            'puedeEditar'     => $permisos['puedeEditar'],
            'puedeDescargar'  => $permisos['puedeDescargar'],
            'help'            => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/rol-comodines.form.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = RolComodinesService::getPermisos();
        $data = RolComodinesService::getList();
        JsonResponse::success('OK', ['data' => $data, 'permisos' => $permisos]);
    }

    public function getDetail()
    {
        $idReporte = (int)Request::input('id', 0);
        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        $detail = RolComodinesService::getDetail($idReporte);
        if (!$detail) {
            JsonResponse::error('Registro no encontrado.');
        }

        JsonResponse::success('OK', $detail);
    }

    public function add()
    {
        $permisos = RolComodinesService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para crear.');
        }

        try {
            $id = RolComodinesService::agregar();
            if (!$id) {
                JsonResponse::error('Error al crear el registro.');
            }

            RolComodinesService::notificarCreacion($id, $permisos['id_usuario']);

            JsonResponse::success('Rol de Comodines creado correctamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al crear: ' . $e->getMessage());
        }
    }

    public function editAssignment()
    {
        $permisos = RolComodinesService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::error('No tienes permisos para editar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? 0);
        $idEstacion = (int)($data['id_estacion'] ?? 0);
        $dia = (int)($data['dia'] ?? 0);

        if (!$idReporte || !$idUsuario || !$dia) {
            JsonResponse::error('Datos incompletos.');
        }

        try {
            $ok = RolComodinesService::editarAsignacion($idReporte, $idUsuario, $idEstacion, $dia);
            if (!$ok) {
                JsonResponse::error('Error al guardar la asignación.');
            }

            RolComodinesService::notificarEdicion($idReporte, $permisos['id_usuario']);

            JsonResponse::success('Asignación guardada correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al editar: ' . $e->getMessage());
        }
    }

    public function saveDates()
    {
        $permisos = RolComodinesService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::error('No tienes permisos para editar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $fechaInicio = trim((string)($data['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($data['fecha_fin'] ?? ''));

        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        try {
            $ok = RolComodinesService::guardarFechas($idReporte, $fechaInicio, $fechaFin);
            if (!$ok) {
                JsonResponse::error('Error al guardar las fechas.');
            }

            JsonResponse::success('Fechas guardadas correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al guardar fechas: ' . $e->getMessage());
        }
    }

    public function finalize()
    {
        $permisos = RolComodinesService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::error('No tienes permisos para editar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $fechaInicio = trim((string)($data['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($data['fecha_fin'] ?? ''));

        if (!$idReporte || !$fechaInicio || !$fechaFin) {
            JsonResponse::error('Las fechas de inicio y término son obligatorias.');
        }

        try {
            $ok = RolComodinesService::finalizar($idReporte, $fechaInicio, $fechaFin);
            if (!$ok) {
                JsonResponse::error('Error al finalizar el rol.');
            }

            RolComodinesService::notificarFinalizacion($idReporte, $permisos['id_usuario'], $fechaInicio, $fechaFin);

            JsonResponse::success('Rol finalizado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al finalizar: ' . $e->getMessage());
        }
    }

    public function delete()
    {
        $permisos = RolComodinesService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        try {
            $ok = RolComodinesService::eliminar($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el registro.');
            }

            RolComodinesService::notificarEliminacion($id, $permisos['id_usuario']);

            JsonResponse::success('Rol eliminado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function pdf(int $idReporte)
    {
        $idReporte = (int)$idReporte;
        if ($idReporte <= 0) {
            $idReporte = (int)Request::input('id', 0);
        }
        if ($idReporte <= 0) {
            http_response_code(400);
            echo 'ID no válido';
            exit;
        }

        $detail = RolComodinesService::getDetail($idReporte);
        if (!$detail) {
            http_response_code(404);
            echo 'Registro no encontrado';
            exit;
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= RolComodinesService::getPdfStyles();
        $html .= '</style></head><body>';
        $html .= RolComodinesService::buildRolComodinesPdfHtml($idReporte);
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fechaInicio = $detail['fecha_inicio'] ?: 'sin-fecha';
        $filename = 'Rol de Comodines (' . $fechaInicio . ').pdf';
        $dompdf->stream($filename, ['Attachment' => 1]);
        exit;
    }
}
