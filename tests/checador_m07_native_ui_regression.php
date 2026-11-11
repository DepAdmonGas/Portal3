<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$render = static function (array $panel, bool $canPost): string {
    $temporaryAccessPanel = $panel;
    $temporaryAccessFeedback = null;
    $canViewTemporaryPin = false;
    $demoMode = false;
    $temporaryAccessPostOperationsEnabled = $canPost;
    $selectedWorkplaceName = 'Localidad ficticia';
    $selectedWorkplaceId = 900001;
    $selectedDate = '2026-10-30';
    $selectedWorkerId = null;
    $_SESSION = [];
    ob_start();
    require $GLOBALS['m07NativeViewPath'];
    return (string) ob_get_clean();
};
$GLOBALS['m07NativeViewPath'] = $viewPath;

$emptyHtml = $render(['state' => 'success', 'requests' => [], 'can_authorize' => false, 'can_cancel' => false, 'can_reject' => false], false);
$emptyDom = new DOMDocument();
@$emptyDom->loadHTML('<!doctype html><html><body>' . $emptyHtml . '</body></html>');
$emptyXpath = new DOMXPath($emptyDom);
$assert($emptyXpath->query('//table')->length === 0
    && str_contains($emptyHtml, 'No hay solicitudes de PIN temporal pendientes.')
    && !str_contains($emptyHtml, 'consulta de la cola terminó'),
    'Successful empty queue uses a neutral message without inventing a result row');

$requestUuidA = '11111111-1111-4111-8111-111111111111';
$requestUuidB = '22222222-2222-4222-8222-222222222222';
$fakeRequest = static function (string $uuid, string $suffix): array {
    return [
        'request_uuid' => $uuid,
        'id_personal_resuelto' => 900100 + (int) $suffix,
        'nombre_trabajador' => 'FICTICIO — sin persona real ' . $suffix,
        'id_estacion' => 900200 + (int) $suffix,
        'dispositivo_nombre' => 'Dispositivo ficticio ' . $suffix,
        'reason' => 'FINGERPRINT_NOT_RECOGNIZED',
        'requested_at_utc' => '2026-10-30 12:00:00',
        'request_expires_at_utc' => '2026-10-30 12:30:00',
    ];
};
$rowsHtml = $render([
    'state' => 'success',
    'can_authorize' => true,
    'can_cancel' => true,
    'can_reject' => false,
    'cancelable_uuid' => $requestUuidA,
    'requests' => [$fakeRequest($requestUuidA, '1'), $fakeRequest($requestUuidB, '2')],
], true);
$rowsDom = new DOMDocument();
@$rowsDom->loadHTML('<!doctype html><html><body>' . $rowsHtml . '</body></html>');
$rowsXpath = new DOMXPath($rowsDom);
$rows = $rowsXpath->query('//table/tbody/tr');
$triggers = $rowsXpath->query('//button[@data-bs-toggle="dropdown"]');
$menus = $rowsXpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " dropdown-menu ")]');
$assert($rows->length === 2 && $triggers->length === 0 && $menus->length === 0,
    'A single authorized row action is rendered directly without a dropdown');

$rowForms = $rowsXpath->query('//table/tbody/tr//form');
$cancelForms = $rowsXpath->query('//form[contains(@action, "/cancel")]');
$rowCheckboxes = $rowsXpath->query('//table/tbody/tr//input[@type="checkbox"]');
$labelsMatch = true;
foreach ($rowCheckboxes as $checkbox) {
    $labelId = $checkbox->getAttribute('id');
    $labelsMatch = $labelsMatch
        && $labelId !== ''
        && $rowsXpath->query('//label[@for=' . "'" . $labelId . "'" . ']')->length === 1
        && $checkbox->hasAttribute('required');
}
$actionButtons = $rowsXpath->query('//table/tbody/tr//button[contains(normalize-space(.), "Autorizar")]');
$assert($rowForms->length === 2
    && $cancelForms->length === 1
    && $actionButtons->length === 2
    && str_contains($actionButtons->item(0)->getAttribute('class'), 'btn-primary'),
    'Palo Solo Encargado receives authorize actions and a separately scoped cancel action, but no reject action');
$assert($labelsMatch
    && $rowsXpath->query('//form[contains(@action, "/reject")]')->length === 0
    && $rowsXpath->query('//form[contains(@action, "/authorize")]')->length === 2,
    'Row forms keep CSRF-bound confirmation and cannot emit a reject action for the narrow Encargado grant');
$assert(str_contains($rowsHtml, 'Sin información de estado')
    && !str_contains($rowsHtml, 'btn-table-action')
    && !str_contains($rowsHtml, 'table-actions-cell')
    && !str_contains($rowsHtml, 'table-actions-group')
    && !str_contains($rowsHtml, 'temporary_pin')
    && !str_contains($rowsHtml, 'Código de un solo uso'),
    'Rows disclose no invented status, parallel action CSS, or PIN content');

$readOnlyHtml = $render([
    'state' => 'success',
    'can_authorize' => false,
    'can_cancel' => false,
    'can_reject' => false,
    'requests' => [$fakeRequest($requestUuidA, '1')],
], false);
$readOnlyDom = new DOMDocument();
@$readOnlyDom->loadHTML('<!doctype html><html><body>' . $readOnlyHtml . '</body></html>');
$readOnlyXpath = new DOMXPath($readOnlyDom);
$assert($readOnlyXpath->query('//table/tbody/tr')->length === 1
    && $readOnlyXpath->query('//button[@data-bs-toggle="dropdown"]')->length === 0
    && $readOnlyXpath->query('//form[contains(@action, "acceso-temporal")]')->length === 0,
    'Read-only profile shows no action button, disabled option, or form');

$legacyHtml = $render([
    'state' => 'success',
    'can_authorize' => true,
    'can_cancel' => true,
    'can_reject' => true,
    'cancelable_uuid' => $requestUuidA,
    'requests' => [$fakeRequest($requestUuidA, '1')],
], true);
$legacyDom = new DOMDocument();
@$legacyDom->loadHTML('<!doctype html><html><body>' . $legacyHtml . '</body></html>');
$legacyXpath = new DOMXPath($legacyDom);
$assert($legacyXpath->query('//button[@data-bs-toggle="dropdown"]')->length === 1
    && $legacyXpath->query('//form[contains(@action, "/authorize")]')->length === 1
    && $legacyXpath->query('//form[contains(@action, "/reject")]')->length === 1,
    'Legacy edit-authorized profile retains its existing authorize/reject actions');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$layout = file_get_contents(__DIR__ . '/../app/Views/layouts/departamento-operativo.php') ?: '';
$assert(str_contains($controller, "Breadcrumb::add('Checador'")
    && str_contains($controller, "Breadcrumb::add(\$workspaceTitles[\$workspace] ?? 'Checador', '')")
    && str_contains($controller, "'temporary-access' => 'PIN temporal'")
    && str_contains($layout, '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>')
    && str_contains($layout, '\\App\\Core\\Breadcrumb::render();'),
    'Institutional title and breadcrumb already express Recursos Humanos → Checador → PIN temporal');

printf("CHECADOR_M07_NATIVE_UI_RESULT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
