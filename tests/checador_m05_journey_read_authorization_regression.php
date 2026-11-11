<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorAccessService;
use App\Services\ChecadorV2PermissionService;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$assert(ChecadorV2PermissionService::JOURNEY_READ === 'checador.jornadas.leer', 'M05 has an independent, exact read permission key');
$assert(ChecadorV2PermissionService::effectiveGrantDecision(null, ['active' => 1, 'granted' => 1]), 'position grant permits M05 read');
$assert(!ChecadorV2PermissionService::effectiveGrantDecision(['active' => 1, 'granted' => 0], ['active' => 1, 'granted' => 1]), 'direct denial takes precedence for M05');
$assert(ChecadorAccessService::journeyReadCapabilityAllowed(6, true, false), 'Encargado needs the narrow grant, not biometricos.leer');
$assert(!ChecadorAccessService::journeyReadCapabilityAllowed(6, false, false), 'Encargado without a read grant is denied');
$assert(ChecadorAccessService::journeyReadCapabilityAllowed(13, false, true), 'Dirección keeps the existing biometricos.read path');
$assert(ChecadorAccessService::journeyReadCapabilityAllowed(13, true, false), 'Dirección may use an explicit M05 grant if assigned');
$assert(!ChecadorAccessService::journeyReadCapabilityAllowed(3, true, true), 'unsupported positions cannot use either M05 path');

$stationScope = [
    ['id' => 1, 'nombre' => 'Interlomas'],
    ['id' => 2, 'nombre' => 'Palo Solo'],
];
$assert(ChecadorAccessService::constrainJourneyStationWorkplaces($stationScope, 1) === [$stationScope[0]], 'requested locality narrows to an authorized physical station');
$assert(ChecadorAccessService::constrainJourneyStationWorkplaces($stationScope, null) === $stationScope, 'unselected supervision remains limited to authorized stations');
$assert(ChecadorAccessService::constrainJourneyStationWorkplaces($stationScope, 9) === null, 'foreign locality is denied');
$assert(ChecadorAccessService::constrainJourneyStationWorkplaces([], null) === null, 'empty station scope is denied');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$assert(is_string($controller)
    && strpos($controller, 'authorizeJourneyRead($requestedWorkplace)') < strpos($controller, 'resolveWorkplaceLocalDay($journeyUser')
    && strpos($controller, 'authorizeJourneyRead($requestedWorkplace)') < strpos($controller, 'readJourneyObservationSet('), 'Portal3 rechecks M05 read before any timezone or PunchEvent API GET');
$assert(is_string($controller) && str_contains($controller, 'No se muestran marcaciones.'), 'API permission failures remain non-empty and fail closed');

echo "RESULT: {$passes} PASS / {$fails} FAIL" . PHP_EOL;
exit($fails === 0 ? 0 : 1);
