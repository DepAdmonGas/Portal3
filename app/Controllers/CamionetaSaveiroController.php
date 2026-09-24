<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\View;
use App\Services\CamionetaSaveiroService;

class CamionetaSaveiroController extends BaseController
{
    public function index(): void
    {
        $title = 'Camioneta Saveiro';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/5-comercializadora/camioneta-saveiro/index', [
            'title'   => $title,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/5-comercializadora/camioneta-saveiro.init.js?v=' . time(),
            ],
            'links'   => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData(): void
    {
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $data = CamionetaSaveiroService::getDocumentos($tipo);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function store(): void
    {
        $file = $_FILES['archivo'] ?? [];
        $result = CamionetaSaveiroService::storeDocumento($_POST, $file);

        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $file = $_FILES['archivo'] ?? null;
        $result = CamionetaSaveiroService::updateDocumento($id, $_POST, $file);

        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? 0);

        $result = CamionetaSaveiroService::deleteDocumento($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function getComentarios(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $data = CamionetaSaveiroService::getComentarios($id);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function storeComentario(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idDocumento = (int)($input['id'] ?? 0);
        $comentario = (string)($input['comentario'] ?? '');

        $result = CamionetaSaveiroService::storeComentario($idDocumento, $comentario);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}