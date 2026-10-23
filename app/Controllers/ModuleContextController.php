<?php
namespace App\Controllers;

use App\Core\Session;
use App\Services\ChecadorAccessService;
use App\Services\ModuleStationService;

class ModuleContextController extends BaseController
{
public function setContext()
{
header('Content-Type: application/json');

try {
$input = json_decode(file_get_contents('php://input'), true);
$moduleKey = is_array($input) ? ($input['module_key'] ?? null) : null;
$idEstacion = is_array($input) && isset($input['id_estacion']) && is_numeric($input['id_estacion']) ? (int)$input['id_estacion'] : null;
$idDepto = is_array($input) && isset($input['id_depto']) && is_numeric($input['id_depto']) ? (int)$input['id_depto'] : null;

if (!$moduleKey) {
echo json_encode(['ok' => false, 'message' => 'module_key requerido']);
return;
}

if ($moduleKey === ChecadorAccessService::MODULE_KEY) {
    $rawStation = $input['id_estacion'] ?? null;
    $rawDepartment = $input['id_depto'] ?? null;
    $validStation = $rawStation === null || $rawStation === ''
        || (is_int($rawStation) && $rawStation > 0)
        || (is_string($rawStation) && preg_match('/^[1-9][0-9]*$/D', $rawStation) === 1);
    $validDepartment = $rawDepartment === null || $rawDepartment === ''
        || (is_int($rawDepartment) && $rawDepartment > 0)
        || (is_string($rawDepartment) && preg_match('/^[1-9][0-9]*$/D', $rawDepartment) === 1);
    $hasStation = $rawStation !== null && $rawStation !== '';
    $hasDepartment = $rawDepartment !== null && $rawDepartment !== '';
    if (!$validStation || !$validDepartment || ($hasStation && $hasDepartment)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'La selección de estación o departamento no está autorizada.']);
        return;
    }

    $selectedId = $hasDepartment ? $idDepto : $idEstacion;
    $access = ChecadorAccessService::authorize($selectedId);
    if (!($access['allowed'] ?? false)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'La estación o departamento seleccionado no está autorizado para Checador.']);
        return;
    }

    $stationIds = array_map('intval', array_column($access['station_workplaces'] ?? [], 'id'));
    $departmentIds = array_map('intval', array_column($access['department_workplaces'] ?? [], 'id'));
    if (($hasStation && !in_array((int) $idEstacion, $stationIds, true))
        || ($hasDepartment && !in_array((int) $idDepto, $departmentIds, true))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'La selección no corresponde al grupo autorizado.']);
        return;
    }

    ModuleStationService::setContext($moduleKey, $hasStation ? $idEstacion : null, $hasDepartment ? $idDepto : null);
    $savedContext = ModuleStationService::getContext($moduleKey);
    if (($hasStation && (int) ($savedContext['id_estacion'] ?? 0) !== $idEstacion)
        || ($hasDepartment && (int) ($savedContext['id_depto'] ?? 0) !== $idDepto)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'No se pudo validar la selección de Checador.']);
        return;
    }

    session_write_close();
    echo json_encode(['ok' => true]);
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
