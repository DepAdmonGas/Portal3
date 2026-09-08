<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ListaNegraService;
use Dompdf\Dompdf;
use Dompdf\Options;

class ListaNegraController extends BaseController
{
    public function index()
    {
        $permisos = ListaNegraService::getPermisos();

        $title = 'Lista Negra';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/lista-negra/index', [
            'title'          => $title,
            'puedeCrear'     => $permisos['puedeCrear'],
            'puedeEditar'    => $permisos['puedeEditar'],
            'puedeEliminar'  => $permisos['puedeEliminar'],
            'puedeDescargar' => $permisos['puedeDescargar'],
            'idUsuario'      => $permisos['id_usuario'],
            'idEstacion'     => $permisos['id_estacion'],
            'nombrePuesto'   => $permisos['nombre_puesto'],
            'personal'       => ListaNegraService::getPersonalDisponible($permisos['id_estacion']),
            'help'           => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/lista-negra.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/lista-negra.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css?v=' . time(),
                '/assets/css/select2-modal.css?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = ListaNegraService::getPermisos();

        $fechaInicio = (string)Request::input('fecha_inicio', '');
        $fechaFin = (string)Request::input('fecha_fin', '');

        $data = ListaNegraService::getList($fechaInicio, $fechaFin);

        JsonResponse::success('OK', ['data' => $data, 'permisos' => $permisos]);
    }

    public function getPersonal()
    {
        $permisos = ListaNegraService::getPermisos();

        $data = ListaNegraService::getPersonalDisponible($permisos['id_estacion']);

        JsonResponse::success('OK', ['data' => $data]);
    }

    public function add()
    {
        $permisos = ListaNegraService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para agregar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = (int)($data['id_personal'] ?? 0);
        $motivo = trim((string)($data['motivo'] ?? ''));
        $detalle = trim((string)($data['detalle'] ?? ''));

        if (!$idPersonal) {
            JsonResponse::error('Selecciona un colaborador.');
        }
        if ($motivo === '') {
            JsonResponse::error('El motivo es obligatorio.');
        }
        if ($detalle === '') {
            JsonResponse::error('La descripción es obligatoria.');
        }

        try {
            $id = ListaNegraService::agregar($idPersonal, $motivo, $detalle);
            if (!$id) {
                JsonResponse::error('Error al agregar el registro.');
            }

            ListaNegraService::notificarCreacion($id, $permisos['id_usuario']);

            JsonResponse::success('Información agregada exitosamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al agregar: ' . $e->getMessage());
        }
    }

    public function delete()
    {
        $permisos = ListaNegraService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $datos = ListaNegraService::getDatosLista($id);
        if (!$datos) {
            JsonResponse::error('Registro no encontrado.');
        }

        try {
            $ok = ListaNegraService::eliminar($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el registro.');
            }

            ListaNegraService::notificarEliminacion($datos, $permisos['id_usuario']);

            JsonResponse::success('Información eliminada exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function getComentarios()
    {
        $id = (int)Request::input('id', 0);
        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $data = ListaNegraService::getComentarios($id);
        JsonResponse::success('OK', ['data' => $data]);
    }

    public function addComentario()
    {
        $permisos = ListaNegraService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para comentar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $comentario = trim((string)($data['comentario'] ?? ''));

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }
        if ($comentario === '') {
            JsonResponse::error('El comentario es obligatorio.');
        }

        try {
            ListaNegraService::addComentario($id, $comentario, $permisos['id_usuario']);
            ListaNegraService::notificarComentario($id, $permisos['id_usuario']);

            JsonResponse::success('Comentario agregado exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al agregar el comentario: ' . $e->getMessage());
        }
    }

    public function getArchivos()
    {
        $id = (int)Request::input('id', 0);
        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $data = ListaNegraService::getArchivos($id);
        JsonResponse::success('OK', ['data' => $data]);
    }

    public function uploadArchivo()
    {
        $permisos = ListaNegraService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para subir archivos.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $file = $_FILES['archivo'] ?? null;

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }
        if ($descripcion === '') {
            JsonResponse::error('La descripción es obligatoria.');
        }
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            JsonResponse::error('Debes seleccionar un archivo.');
        }

        try {
            $ok = ListaNegraService::addArchivo($id, $descripcion, $file);
            if (!$ok) {
                JsonResponse::error('Error al agregar el archivo.');
            }

            JsonResponse::success('Archivo agregado exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al subir el archivo: ' . $e->getMessage());
        }
    }

    public function deleteArchivo()
    {
        $permisos = ListaNegraService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar archivos.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        try {
            $ok = ListaNegraService::deleteArchivo($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el archivo.');
            }

            JsonResponse::success('Archivo eliminado exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar el archivo: ' . $e->getMessage());
        }
    }

    public function pdf()
    {
        $fechaInicio = (string)Request::input('fecha_inicio', '');
        $fechaFin = (string)Request::input('fecha_fin', '');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= ListaNegraService::getPdfStyles();
        $html .= '</style></head><body>';
        $html .= ListaNegraService::buildPdfHtml($fechaInicio, $fechaFin);
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dir = dirname(__DIR__, 2) . '/public/uploads/archivos/lista-negra/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach (glob($dir . 'lista-negra-pdf-*.pdf') ?: [] as $viejo) {
            @unlink($viejo);
        }

        $archivo = 'lista-negra-pdf-' . date('Ymd-His') . '.pdf';
        file_put_contents($dir . $archivo, $dompdf->output());

        JsonResponse::success('OK', ['archivo' => $archivo]);
    }
}