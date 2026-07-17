<?php
namespace App\Controllers;

use App\Core\Session;
use App\Services\ModuleStationService;

class ModuleContextController extends BaseController
{
public function setContext()
{
header('Content-Type: application/json');

try {
$input = json_decode(file_get_contents('php://input'), true);
$moduleKey = $input['module_key'] ?? null;
$idEstacion = isset($input['id_estacion']) && is_numeric($input['id_estacion']) ? (int)$input['id_estacion'] : null;
$idDepto = isset($input['id_depto']) && is_numeric($input['id_depto']) ? (int)$input['id_depto'] : null;

if (!$moduleKey) {
echo json_encode(['ok' => false, 'message' => 'module_key requerido']);
return;
}

ModuleStationService::setContext($moduleKey, $idEstacion, $idDepto);

session_write_close();

echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
}
}
