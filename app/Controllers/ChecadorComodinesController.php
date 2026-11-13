<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorV2PermissionService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ChecadorRolComodinesService;

/**
 * Compatibility boundary for the pre-Checador Rol de Comodines URLs.
 *
 * The old screens and exports read all assigned accounts and stations, and the
 * old write methods cannot verify the canonical worker relationship. Keep the
 * URLs closed until those contracts can be satisfied; the authorized read-only
 * entry point is the station-scoped Checador view.
 */
final class ChecadorComodinesController extends BaseController
{
    private const MODULE_KEY = 'recursos-humanos';
    private const CHECADOR_URL = '/departamento-operativo/recursos-humanos/checador/rol-comodines';

    public function index(): void
    {
        $this->redirectLegacyRead();
    }

    public function form(int $idReporte): void
    {
        $this->redirectLegacyRead();
    }

    public function getData(): void
    {
        $this->closeLegacyJsonRead();
    }

    public function getDetail(): void
    {
        $this->closeLegacyJsonRead();
    }

    public function add(): void
    {
        $this->closeLegacyWrite('crear');
    }

    public function editAssignment(): void
    {
        $this->closeLegacyWrite('editar');
    }

    public function saveDates(): void
    {
        $this->closeLegacyWrite('editar');
    }

    public function finalize(): void
    {
        $this->closeLegacyWrite('editar');
    }

    public function delete(): void
    {
        $this->closeLegacyWrite('eliminar');
    }

    /** Read canonical coverage state from the shared MariaDB source of truth. */
    public function coverageList(): void
    {
        $user = Auth::user();
        if (!$user) JsonResponse::unauthorized();
        $this->authorizeCoverageActor($user, ChecadorV2PermissionService::COMODINES_READ);
        if (!$this->portalScopeReady()) JsonResponse::forbidden('No tienes acceso a esta consulta de Checador.');

        $result = (new \App\Services\RolComodinesPortalWriterService())->listVisible($this->authorizedWorkplaceIds());
        if (!empty($result['ok'])) {
            $items = $result['data']['items'] ?? [];
            $result['data']['presentations'] = ChecadorRolComodinesService::coveragePresentationRows(is_array($items) ? $items : []);
        }
        $this->sendApiResult($result, 'No se pudieron consultar las coberturas canónicas.');
    }

    /** Read a canonical record and its revision/audit history from MariaDB. */
    public function coverageDetail(string $coverageUuid): void
    {
        $user = Auth::user();
        if (!$user) JsonResponse::unauthorized();
        $this->authorizeCoverageActor($user, ChecadorV2PermissionService::COMODINES_READ);
        if (!$this->portalScopeReady()) JsonResponse::forbidden('No tienes acceso a esta consulta de Checador.');

        $result = (new \App\Services\RolComodinesPortalWriterService())->detail($coverageUuid, $this->authorizedWorkplaceIds());
        if (!empty($result['ok'])) {
            $coverage = $result['data'];
            $scope = $this->authorizedWorkplaceIds();
            // Direction must be authorized for both Workplaces represented by the record.
            if (!in_array((int) ($coverage['id_home_workplace'] ?? 0), $scope, true)
                || !in_array((int) ($coverage['id_coverage_workplace'] ?? 0), $scope, true)) {
                JsonResponse::forbidden('La cobertura no está dentro del alcance institucional autorizado.');
            }
            $presentation = ChecadorRolComodinesService::coveragePresentationRows([$coverage])[0] ?? [];
            $result['data']['presentation'] = [
                'worker_label' => $presentation['worker_label'] ?? 'Trabajador no disponible',
                'home_label' => $presentation['home_label'] ?? 'Localidad de origen no disponible',
                'coverage_label' => $presentation['coverage_label'] ?? 'Localidad de cobertura no disponible',
            ];
        }
        $this->sendApiResult($result, 'No se pudo consultar el historial de la cobertura.');
    }

    /** Search active workers whose canonical home membership is in Direction's effective scope. */
    public function searchCoverageWorkers(): void
    {
        $user = Auth::user();
        if (!$user) JsonResponse::unauthorized();
        $this->authorizeCoverageActor($user, ChecadorV2PermissionService::COMODINES_REQUEST);
        if (!$this->portalScopeReady()) JsonResponse::forbidden('No tienes acceso a esta consulta de Checador.');
        $query = Request::query();
        $authorizedWorkplaceIds = $this->authorizedWorkplaceIds();
        if ($authorizedWorkplaceIds === []) {
            JsonResponse::forbidden('No hay localidades dentro del alcance autorizado para buscar trabajadores.');
        }
        $result = ChecadorRolComodinesService::searchCoverageWorkers(
            $authorizedWorkplaceIds,
            is_scalar($query['q'] ?? null) ? (string) $query['q'] : '',
            is_scalar($query['fecha_desde_local'] ?? null) ? (string) $query['fecha_desde_local'] : '',
            is_scalar($query['fecha_hasta_local_exclusiva'] ?? null) ? (string) $query['fecha_hasta_local_exclusiva'] : ''
        );
        if (empty($result['ok'])) {
            $invalid = ($result['reason'] ?? '') === 'invalid_worker_search';
            $tooBroad = ($result['reason'] ?? '') === 'worker_search_limit';
            JsonResponse::error(
                $invalid
                    ? 'Selecciona un periodo válido y revisa el texto de búsqueda.'
                    : ($tooBroad
                        ? 'La búsqueda es demasiado amplia. Afínala por nombre, ID RH o número de colaborador.'
                        : 'No se pudo consultar el origen de candidatos.'),
                ($invalid || $tooBroad) ? 422 : 503,
                ['code' => $invalid ? 'worker_search_invalid' : ($tooBroad ? 'worker_search_too_broad' : 'worker_search_unavailable')]
            );
        }
        JsonResponse::send([
            'success' => true,
            'workers' => $result['workers'] ?? [],
            'has_more' => (bool) ($result['has_more'] ?? false),
            'eligibility_notice' => $result['eligibility_notice'] ?? null,
            'candidate_count' => count($result['workers'] ?? []),
            'eligible_count' => count(array_filter($result['workers'] ?? [], static fn(array $worker): bool => !empty($worker['eligible']))),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** Dirección submits a pending request through the Portal3 canonical database writer. */
    public function requestCoverage(): void
    {
        $user = Auth::user();
        if (!$user) JsonResponse::unauthorized();
        $this->authorizeCoverageActor($user, ChecadorV2PermissionService::COMODINES_REQUEST);

        $input = Request::json();
        if (!$this->portalScopeReady()) JsonResponse::forbidden('No tienes acceso a esta operación de Checador.');
        $homeWorkplaceId = filter_var(
            $input['id_home_workplace'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($homeWorkplaceId === false
            || !in_array((int) $homeWorkplaceId, $this->authorizedWorkplaceIds(), true)) {
            JsonResponse::forbidden('La localidad de origen no está dentro del alcance autorizado.');
        }

        $idempotencyKey = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $result = (new \App\Services\RolComodinesPortalWriterService())->createRequest(
            (int) $user->id,
            $this->authorizedWorkplaceIds(),
            $input,
            $idempotencyKey
        );
        $this->sendApiResult($result, 'No se pudo enviar la solicitud de cobertura.');
    }

    /** Dirección authorizes only records whose origin and destination are both in the server session scope. */
    public function authorizeCoverage(string $coverageUuid): void
    {
        $this->transitionCoverage($coverageUuid, 'authorize');
    }

    /** Direction-only cancellation through the Portal3 canonical database writer. */
    public function cancelCoverage(string $coverageUuid): void
    {
        $this->transitionCoverage($coverageUuid, 'cancel');
    }

    private function transitionCoverage(string $coverageUuid, string $action): void
    {
        $user = Auth::user();
        if (!$user) JsonResponse::unauthorized();
        $capability = $action === 'authorize'
            ? ChecadorV2PermissionService::COMODINES_APPROVE
            : ChecadorV2PermissionService::COMODINES_CANCEL;
        $this->authorizeCoverageActor($user, $capability);
        $input = Request::json();
        $result = (new \App\Services\RolComodinesPortalWriterService())->transition(
            $coverageUuid,
            strtoupper($action),
            (int) $user->id,
            $this->authorizedWorkplaceIds(),
            (string) ($input['motivo'] ?? ''),
            trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''))
        );
        $this->sendApiResult($result, 'No se pudo actualizar la cobertura.');
    }

    private function portalScopeReady(): bool
    {
        $scope = ChecadorAccessService::authorize(null);
        return !empty($scope['allowed']) && $this->validatedSessionWorkplace(null) !== null;
    }

    private function authorizeCoverageActor(\App\Models\Usuario $user, string $permission): void
    {
        $positionId = (int) ($user->id_puesto ?? 0);
        $userId = (int) ($user->id ?? 0);
        if ($positionId !== 13 || !ChecadorV2PermissionService::allowsComodines($userId, $positionId, $permission)) {
            JsonResponse::forbidden('Esta función está disponible únicamente para Dirección con permiso efectivo.');
        }
    }

    /** @return list<int> */
    private function authorizedWorkplaceIds(): array
    {
        $scope = ChecadorAccessService::authorize(null);
        // Checador returns the union of canonical station and explicitly granted
        // department localities. Comodines is already limited to Dirección by
        // authorizeCoverageActor(), so do not discard an authorized department
        // such as Autolavado (9) by projecting only physical station workplaces.
        $workplaces = is_array($scope['workplaces'] ?? null) ? $scope['workplaces'] : [];
        return !empty($scope['allowed'])
            ? array_values(array_unique(array_filter(array_map(
                static fn(array $item): int => (int) ($item['id'] ?? 0),
                $workplaces
            ), static fn(int $id): bool => $id > 0)))
            : [];
    }

    private function sendApiResult(array $result, string $fallbackMessage): never
    {
        if (!empty($result['ok'])) {
            JsonResponse::send([
                'success' => true,
                'data' => $result['data'] ?? [],
            ], (int) ($result['http_status'] ?? 200), ['Cache-Control' => 'private, no-store']);
        }

        $status = (int) ($result['http_status'] ?? 503);
        $code = (string) ($result['code'] ?? 'coverage_operation_failed');
        $message = match ($code) {
            'feature_disabled' => 'Las operaciones administrativas de cobertura permanecen desactivadas.',
            'function_permission_denied' => 'Tu permiso efectivo no autoriza esta operación.',
            'station_scope_denied', 'coverage_scope_denied' => 'La localidad no está dentro del alcance autorizado.',
            'coverage_destination_not_authorized' => 'La localidad seleccionada no está autorizada como destino de cobertura.',
            'coverage_destination_mapping_unavailable' => 'No se pudo confirmar el vínculo vigente de la estación de destino.',
            'worker_candidate_not_eligible' => 'El trabajador no cumple el origen, estado de estación o membresía requeridos para este periodo.',
            'worker_membership_unverified' => 'No se pudo confirmar la adscripción del trabajador para todo el periodo.',
            'writer_database_not_local' => 'La escritura administrativa solo está disponible en la base local de desarrollo autorizada.',
            'coverage_state_conflict' => 'El estado de la cobertura cambió; actualiza la consulta antes de continuar.',
            'idempotency_key_conflict' => 'La operación ya se intentó con datos distintos.',
            'coverage_assignment_conflict', 'coverage_overlap_conflict' => 'El periodo se cruza con otra programación; no se guardó ningún cambio.',
            'coverage_not_future' => 'La cobertura debe iniciar después de la fecha local actual.',
            'coverage_consolidated_date_conflict' => 'El periodo ya tiene jornadas consolidadas y no puede modificarse.',
            'workplace_timezone_unresolved' => 'No se pudo confirmar la zona horaria vigente de la localidad.',
            'coverage_template_limit', 'coverage_template_unavailable' => 'No se pudo confirmar el catálogo de turnos vigente.',
            'coverage_read_failed' => 'No se pudieron consultar las coberturas canónicas.',
            default => $fallbackMessage,
        };
        JsonResponse::error($message, in_array($status, [400, 401, 403, 404, 409, 422, 503], true) ? $status : 502, [
            'code' => $code,
        ]);
    }

    /** A technical AJAX/form locality must match the institutional session context. */
    private function validatedSessionWorkplace(mixed $submitted): ?int
    {
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $sessionId = filter_var(
            $context['id_depto'] ?? $context['id_estacion'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($sessionId === false || $sessionId === null) {
            return null;
        }
        if ($submitted !== null && $submitted !== '') {
            $submittedId = filter_var($submitted, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($submittedId === false || (int) $submittedId !== (int) $sessionId) {
                return null;
            }
        }
        return !empty(ChecadorAccessService::authorize((int) $sessionId)['allowed'])
            ? (int) $sessionId
            : null;
    }

    public function pdf(?int $idReporte = null): void
    {
        $authorization = $this->authorizeLegacyOperation('descargar');
        if (empty($authorization['allowed'])) {
            http_response_code(403);
            echo 'Acceso denegado.';
            return;
        }

        // The legacy PDF includes all stations and account names. There is no
        // safe station-scoped export contract yet, so never render it here.
        http_response_code(410);
        echo 'La descarga legacy está deshabilitada. Consulta la vista segura de Checador.';
    }

    private function redirectLegacyRead(): void
    {
        $authorization = $this->authorizeLegacyOperation('leer');
        if (empty($authorization['allowed'])) {
            http_response_code(403);
            echo 'Acceso denegado.';
            return;
        }

        header('Location: ' . self::CHECADOR_URL, true, 302);
    }

    private function closeLegacyJsonRead(): void
    {
        $authorization = $this->authorizeLegacyOperation('leer');
        if (empty($authorization['allowed'])) {
            JsonResponse::forbidden('Acceso denegado.');
        }

        JsonResponse::error(
            'La consulta legacy está deshabilitada. Usa la vista de Checador con estación autorizada.',
            410,
            ['code' => 'legacy_read_unavailable']
        );
    }

    private function closeLegacyWrite(string $action): void
    {
        $authorization = $this->authorizeLegacyOperation($action);
        if (empty($authorization['allowed'])) {
            JsonResponse::forbidden('Acceso denegado.');
        }

        // Permission alone is insufficient while the account-to-worker source
        // and complete row-level station validation remain unresolved.
        JsonResponse::error(
            'La escritura está bloqueada hasta validar el vínculo canónico y el alcance de todas las asignaciones.',
            409,
            ['code' => 'programming_write_blocked']
        );
    }

    /** Resolve the existing Checador and institutional grant for every request. */
    private function authorizeLegacyOperation(string $action): array
    {
        if (!in_array($action, ['leer', 'crear', 'editar', 'eliminar', 'descargar'], true)) {
            return ['allowed' => false, 'reason' => 'unknown_action'];
        }

        $scope = ChecadorAccessService::authorize();
        if (empty($scope['allowed'])) {
            return ['allowed' => false, 'reason' => (string) ($scope['reason'] ?? 'checador_access_denied')];
        }

        $authenticatedUser = Auth::user();
        $userId = (int) ($authenticatedUser->id ?? 0);
        $positionId = (int) ($authenticatedUser->id_puesto ?? 0);
        if ($userId < 1) {
            return ['allowed' => false, 'reason' => 'unauthenticated'];
        }

        $coverageCapability = match ($action) {
            'leer' => ChecadorV2PermissionService::COMODINES_READ,
            'crear' => ChecadorV2PermissionService::COMODINES_REQUEST,
            'editar' => ChecadorV2PermissionService::COMODINES_APPROVE,
            'eliminar' => ChecadorV2PermissionService::COMODINES_CANCEL,
            'descargar' => ChecadorV2PermissionService::COMODINES_READ,
            default => '',
        };
        if ($positionId !== 13 || $coverageCapability === ''
            || !ChecadorV2PermissionService::allowsComodines($userId, $positionId, $coverageCapability)) {
            return ['allowed' => false, 'reason' => 'comodines_permission_denied'];
        }

        $effectivePermissions = ModuloDptoOperativoService::getPermiso($userId, self::MODULE_KEY);
        if (empty($effectivePermissions[$action])) {
            return ['allowed' => false, 'reason' => 'institutional_permission_denied'];
        }
        if ($action === 'descargar' && empty($scope['descargar'])) {
            return ['allowed' => false, 'reason' => 'checador_download_denied'];
        }

        return ['allowed' => true, 'reason' => 'authorized'];
    }
}
