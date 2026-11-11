<?php

declare(strict_types=1);

$portalRoot = dirname(__DIR__);
$apiRoot = dirname($portalRoot) . '/portal-api-v.1.0.1';
$read = static fn(string $path): string => (string) file_get_contents($path);
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};
$slice = static function (string $source, string $start, string $end): string {
    $a = strpos($source, $start);
    if ($a === false) return '';
    $b = strpos($source, $end, $a + strlen($start));
    return substr($source, $a, $b === false ? null : $b - $a);
};

$access = $read($portalRoot . '/app/Services/ChecadorAccessService.php');
$permissions = $read($portalRoot . '/app/Services/ChecadorV2PermissionService.php');
$configurationRead = $read($portalRoot . '/app/Services/ChecadorConfigurationReadService.php');
$administrationRead = $read($portalRoot . '/app/Services/ChecadorM11AdministrationReadService.php');
$bioService = $read($portalRoot . '/app/Services/BiometricosService.php');
$controller = $read($portalRoot . '/app/Controllers/ChecadorController.php');
$view = $read($portalRoot . '/app/Views/departamento-operativo/2-recursos-humanos/checador/configuration.php');
$policyUi = $read($portalRoot . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance-policy.init.js');
$readerUi = $read($portalRoot . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-reader-profile.init.js');
$routes = $read($portalRoot . '/routes/web.php');
$apiAcl = $read($apiRoot . '/modelo/v2/AttendancePolicyAuthorizationService.php');
$migration = $read($apiRoot . '/migraciones/v2/2026_11_03_000004_m11_complete_configuration.sql');
$addendumPortal = $read($portalRoot . '/docs/v2/CHECADOR_V2_M11_COMPLETE_CONFIGURATION_ADDENDUM_V1.0.md');
$addendumApi = $read($apiRoot . '/docs/v2/CHECADOR_V2_M11_COMPLETE_CONFIGURATION_ADDENDUM_V1.0.md');

$readerRead = $slice($bioService, 'public static function readM11ReaderProfiles(', 'public static function updateM11ReaderProfile(');
$readerUpdate = $slice($bioService, 'public static function updateM11ReaderProfile(', 'private static function updateReaderProfileRecord(');
$readerWriter = $slice($controller, 'public function updateM11ReaderProfile(): void', 'public function saveM11Position(): void');
$policyAuth = $slice($access, 'public static function authorizeAttendancePolicy(', 'public static function authorizeReaderProfiles(');
$readerAuth = $slice($access, 'public static function authorizeReaderProfiles(', 'public static function authorizePositionCatalog(');
$positionAuth = $slice($access, 'public static function authorizePositionCatalog(', 'private static function authorizeM11StationCapability(');

$assert(str_contains($policyAuth, 'authorizeM11StationCapability') && str_contains($readerAuth, 'authorizeM11StationCapability')
    && str_contains($access, '[self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID]'), 'policy and reader operations use dedicated M11 grants and server-derived station scope for positions 6/13');
$assert(str_contains($positionAuth, 'self::DIRECCION_OPERACIONES_PUESTO_ID') && str_contains($positionAuth, 'POSITION_CATALOG_EDIT'), 'institutional position catalog is restricted to Direction with its M11-specific permission');
$assert(str_contains($readerRead, "get(['id', 'usuario'])") && !str_contains($readerRead, 'password') && !str_contains($readerRead, 'token')
    && str_contains($readerRead, 'limit(21)'), 'reader projection is station-scoped, bounded and excludes password/token columns');
$assert(str_contains($readerUpdate, 'resolveUniquePhysicalStation') && str_contains($readerUpdate, "where('id_estacion', $" . 'physicalStationId')
    && str_contains($readerUpdate, "where('id', $" . 'profileId') && str_contains($readerUpdate, 'ROTATE_SECRET')
    && !str_contains($readerUpdate, 'password_confirm') && str_contains($readerUpdate, 'ChecadorM11ConfigurationAuditService::record'), 'reader rotation resolves canonical locality, constrains profile ID, and audits metadata only');
$assert(str_contains($view, 'type="password"') && !preg_match('/name="(?:new_password|password)"[^>]*value=/i', $view)
    && !str_contains($view, 'biometricos/configuracion/'), 'integrated UI has blank secret inputs and no Biométricos Legacy navigation');
$assert(str_contains($view, 'canEditAttendancePolicy') && str_contains($view, 'canEditReaderProfiles')
    && str_contains($view, 'canEditPositionCatalog') && str_contains($view, 'Retardos e incidencias')
    && str_contains($view, 'id="people-positions"') && str_contains($view, 'id="reader-access"')
    && str_contains($view, '<?php if ($canReadPositions): ?>')
    && !str_contains($view, '<h2 class="h5 mb-0 text-white" id="reader-access-heading">Lectores y accesos</h2>')
    && !str_contains($view, 'Acceso de lectores') && !str_contains($view, 'No integrado')
    && !str_contains($view, 'id="schedule-configuration"') && !str_contains($view, 'id="attendance-rules"')
    && !str_contains($view, 'biometricos/configuracion/'), 'M11 keeps policy, reader and position functions without duplicate schedule or Legacy-rule sections');
$assert(str_contains($view, 'm11ReaderProfile()') && str_contains($view, 'm11-reader-profile-modal')
    && str_contains($view, 'btn btn-primary btn-sm') && str_contains($view, 'data-save-url=')
    && !preg_match('/name="new_password"[^>]*value=/i', $view), 'reader profile is compact with an Encargado-only modal and no secret in initial HTML');
$assert(str_contains($readerUi, 'axios.post') && str_contains($readerUi, 'X-CSRF-TOKEN')
    && str_contains($readerUi, 'this.loading') && str_contains($readerUi, 'body.profile')
    && !str_contains($readerUi, 'console.') && !str_contains($readerUi, 'password\' =>'), 'reader modal uses the existing writer with CSRF, duplicate-submit guard and readback-only UI update');
$assert(str_contains($readerWriter, "authorizeReaderProfiles($" . 'workplaceId, \'editar\'')
    && str_contains($readerWriter, 'readM11ReaderProfiles($workplaceId)')
    && str_contains($readerWriter, "'profile' => \$confirmed")
    && !str_contains($readerWriter, "'password' =>") && str_contains($readerWriter, 'self::redirect('), 'reader writer preserves server scope and traditional POST while confirming AJAX by username-only readback');
$assert(str_contains($view, '<?php if ($canReadPositions): ?>')
    && str_contains($view, 'id="m11-effective-permissions"')
    && str_contains($view, 'id="m11-change-history"')
    && str_contains($administrationRead, 'authorizeAttendancePolicy($workplaceId, \'leer\')')
    && str_contains($administrationRead, 'authorizePositionCatalog($workplaceId, \'leer\')')
    && str_contains($administrationRead, "where('workplace_id', $" . 'workplaceId')
    && str_contains($administrationRead, 'readCurrentUserPermissions'), 'M11 admin reads are authenticated, station-scoped, self-only for permission review, and limited to existing audit rows');
$policyView = $slice($view, 'id="attendance-policy-v2"', 'id="people-positions"');
$policyWriter = $slice($controller, 'public function saveAttendancePolicy(): void', 'public function updateM11ReaderProfile(): void');
$assert(str_contains($policyView, 'Configuración vigente') && str_contains($policyView, 'Próxima configuración')
    && str_contains($policyView, 'Editar configuración') && str_contains($policyView, 'Historial de cambios')
    && !preg_match('/legacy|versión V2|API V2|versionado/i', $policyView), 'policy UI presents administrative states without Legacy or implementation-specific text');
$assert(str_contains($policyView, 'attendancePolicyForm()') && str_contains($policyView, 'attendancePolicyEditor()')
    && str_contains($policyView, 'name="idempotency_key"') && str_contains($policyView, 'method="post"'), 'policy editor retains Alpine, CSRF-protected traditional POST and idempotency field');
$assert(str_contains($policyUi, 'axios.post') && str_contains($policyUi, "Accept: 'application/json'")
    && str_contains($policyUi, 'form.checkValidity()') && str_contains($policyUi, 'this.loading')
    && str_contains($policyUi, 'attendance-policy-confirmed') && str_contains($policyUi, '409:')
    && str_contains($policyUi, '422:'), 'Alpine/Axios submit validates, locks duplicate submissions, and updates only from successful readback');
$assert(str_contains($policyWriter, "HTTP_ACCEPT") && str_contains($policyWriter, 'application/json')
    && str_contains($policyWriter, 'Cache-Control: no-store') && str_contains($policyWriter, 'readAttendancePolicy($user, $workplaceId, $effectiveFrom)')
    && str_contains($policyWriter, 'if (!$confirmed)') && str_contains($policyWriter, "'next_idempotency_key'")
    && str_contains($policyWriter, 'self::redirect($redirect)'), 'writer negotiates JSON for Axios after readback while preserving the traditional redirect flow');
$assert(str_contains($configurationRead, "'policy_history'") && str_contains($configurationRead, 'readAttendancePolicy($user, $workplaceId, $beforeDate)')
    && str_contains($configurationRead, '$historyReads < 50') && str_contains($configurationRead, "'policy_history_complete'")
    && str_contains($configurationRead, 'readCurrentAttendancePolicy($user, $workplaceId)'), 'configuration history uses existing authorized effective-date GETs with a bounded completeness state');
$assert(str_contains($routes, "Route::auth(['ChecadorController', 'updateM11ReaderProfile'])")
    && str_contains($routes, "Route::auth(['ChecadorController', 'saveM11Position'])"), 'M11 write routes retain authentication and CSRF middleware');
$assert(str_contains($controller, 'hash_equals($password, $passwordConfirm)')
    && str_contains($controller, 'm11ConfigurationPostOperationsEnabled()'), 'credential confirmation and independent local M11 gate are enforced before write');
$assert(str_contains($bioService, 'LOWER(TRIM(puesto))') && str_contains($bioService, 'GET_LOCK')
    && !str_contains($bioService, 'public static function deleteM11Position'), 'existing position catalog blocks duplicate active names and exposes no M11 deletion');
$assert(str_contains($apiAcl, 'Portal3JwtAuthHelper::ENCARGADO_PUESTO_ID')
    && str_contains($apiAcl, 'Portal3JwtAuthHelper::DIRECCION_OPERACIONES_PUESTO_ID'), 'API policy writer recognizes only the two authorized M11 position IDs before specific grant/scope checks');
$assert(str_contains($migration, "('checador.configuracion.lectores.editar', 'POSITION', 6")
    && str_contains($migration, "('checador.configuracion.puestos.editar', 'POSITION', 13")
    && !str_contains($migration, 'checador.plantillas.administrar') && !str_contains($migration, 'biometricos.editar'), 'additive M11 grants do not grant global template administration or broad Biométricos privileges');
$assert(hash('sha256', $addendumPortal) === hash('sha256', $addendumApi), 'M11 governance addenda are byte-identical across Portal3 and API repositories');
$legacyPolicyReader = $slice($configurationRead, 'private static function readM11LegacyPolicyReference(', 'private function templateAdministrationAllowed(');
$assert(str_contains($configurationRead, 'readM11LegacyPolicyReference') && str_contains($legacyPolicyReader, "first(['retardo', 'incidencia'])")
    && str_contains($legacyPolicyReader, 'resolveUniquePhysicalStation') && str_contains($legacyPolicyReader, "where('id_estacion', $" . 'physicalId')
    && !str_contains($legacyPolicyReader, 'update(') && !str_contains($legacyPolicyReader, 'insert('), 'M11 legacy values remain read-only references scoped by unique numlista mapping');

printf("CHECADOR_M11_CONFIGURATION: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
