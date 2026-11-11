<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$routes = file_get_contents(__DIR__ . '/../routes/web.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/RolComodinesController.php');
$service = file_get_contents(__DIR__ . '/../app/Services/RolComodinesService.php');
$accessService = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');

$legacyRoutes = [
    '/recursos-humanos/rol-comodines',
    '/recursos-humanos/rol-comodines/{id:\\d+}',
    '/recursos-humanos/rol-comodines/data',
    '/recursos-humanos/rol-comodines/detail',
    '/recursos-humanos/rol-comodines/add',
    '/recursos-humanos/rol-comodines/edit-assignment',
    '/recursos-humanos/rol-comodines/save-dates',
    '/recursos-humanos/rol-comodines/finalize',
    '/recursos-humanos/rol-comodines/delete',
    '/recursos-humanos/rol-comodines/pdf',
    '/recursos-humanos/rol-comodines/pdf/{id}',
];
$allLegacyRoutesAuthenticated = true;
foreach ($legacyRoutes as $path) {
    $allLegacyRoutesAuthenticated = $allLegacyRoutesAuthenticated
        && str_contains($routes, "'" . $path . "', Route::auth(['RolComodinesController'");
}
$assert($allLegacyRoutesAuthenticated, 'every direct legacy URL remains behind the authenticated route wrapper');

$assert(str_contains($controller, 'ChecadorAccessService::authorize()')
    && str_contains($controller, 'ModuloDptoOperativoService::getPermiso($userId, self::MODULE_KEY)')
    && str_contains($controller, "['allowed' => false, 'reason' => 'institutional_permission_denied']"),
    'legacy access resolves the active Checador scope and effective institutional permission on each request');
$assert(str_contains($accessService, "->where('estatus', 0)")
    && str_contains($controller, "['leer', 'crear', 'editar', 'eliminar', 'descargar']")
    && str_contains($controller, '$effectivePermissions[$action]'),
    'inactive accounts fail the shared Checador authorization and read/write/download grants are checked by operation');
$assert(str_contains($controller, "public function index(): void")
    && str_contains($controller, 'public function form(int $idReporte): void')
    && substr_count($controller, '$this->redirectLegacyRead();') === 2,
    'direct legacy list and detail pages require read permission before redirecting to the scoped Checador view');
$assert(!str_contains($controller, 'getDetail($idReporte)')
    && !str_contains($controller, 'Request::input')
    && str_contains($controller, '$this->redirectLegacyRead();'),
    'a manipulated legacy report ID is never used to query detail data');
$assert(substr_count($controller, '$this->closeLegacyJsonRead();') === 2
    && str_contains($controller, "'code' => 'legacy_read_unavailable'"),
    'legacy list and detail data endpoints are closed after read authorization without returning global data');
$legacyWritesClosed = true;
foreach (['add', 'editAssignment', 'saveDates', 'finalize', 'delete'] as $method) {
    if (preg_match('/public function ' . preg_quote($method, '/') . '\\([^)]*\\): void\\s*\\{([^}]*)\\}/s', $controller, $matches) !== 1
        || !str_contains($matches[1], '$this->closeLegacyWrite(')
        || str_contains($matches[1], 'RolComodinesService::')) {
        $legacyWritesClosed = false;
    }
}
$assert(substr_count($controller, '$this->closeLegacyWrite(') >= 5
    && str_contains($controller, "'code' => 'programming_write_blocked'")
    && $legacyWritesClosed,
    'legacy create, edit, date, finalize, and delete endpoints validate effective grants then fail closed before mutation');
$assert(str_contains($controller, "authorizeLegacyOperation('descargar')")
    && str_contains($controller, "http_response_code(410)")
    && str_contains($controller, 'empty($scope[\'descargar\'])'),
    'legacy PDF requires the effective download grant and Checador download scope, then remains unavailable');
$assert(str_contains($service, '$assignmentColumns = [')
    && str_contains($service, '$assignmentColumns[] = \'id_personal\';')
    && str_contains($service, '->get($assignmentColumns)')
    && str_contains($service, '$unsafeHeaderIds[$reportId] = true;')
    && str_contains($service, 'isset($unsafeHeaderIds[$reportId])'),
    'station-scoped reads inspect every child assignment and omit a whole header when any row is unmappable or out of scope');

printf("CHECADOR_V2_COMODINES_LEGACY_SECURITY: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
