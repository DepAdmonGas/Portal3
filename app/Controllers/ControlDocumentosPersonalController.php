<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Auth;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Session;
use App\Services\ControlDocumentosPersonalService;
use App\Services\RhDirectCreateAuthorizationException;
use App\Services\RhDirectCreateIdempotencyConflict;
use App\Services\PersonalExcelService;
use App\Services\ModuleStationService;
use App\Services\WorkerWorkplaceAssignmentBaselineImportService;
use App\Services\WorkerWorkplaceAssignmentBaselinePreviewService;
use App\Services\WorkerWorkplaceAssignmentHistoryWriteGate;
use App\Services\KpiPersonalService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\RhPuestos;

class ControlDocumentosPersonalController extends BaseController
{
    private const CHECADOR_BASELINE_PREVIEW_SESSION_KEY = 'checador_v2_membership_baseline_preview';

    public function index()
    {
        $permisos = ControlDocumentosPersonalService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        $sessionUsuario = \App\Core\Session::get('usuario');
        if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
            $idEstacion = 0;
        }

        $title = 'Control de Documentos del Personal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess('control-documentos-personal', $title, 'departamento-operativo')) {
            return;
        }

        // The RH source stores canonical op_rh_localidades IDs. Only expose
        // authorized physical-station contexts to the baseline workflow.
        try {
            $baselineWorkplaceIds = (new WorkerWorkplaceAssignmentBaselinePreviewService())->authorizedStationWorkplaceIds();
            $baselineContext = ModuleStationService::getContext('control-documentos-personal');
            $baselineCurrentWorkplaceId = ($baselineContext['id_depto'] ?? null) === null
                ? (int) ($baselineContext['id_estacion'] ?? 0)
                : 0;
        } catch (\Throwable) {
            // A failed scope lookup hides the optional action but leaves the RH page usable.
            $baselineWorkplaceIds = [];
            $baselineCurrentWorkplaceId = 0;
        }
        if (!in_array($baselineCurrentWorkplaceId, $baselineWorkplaceIds, true)) {
            $baselineCurrentWorkplaceId = 0;
        }

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => 'control-documentos-personal',
            'baselineWorkplaceIds' => $baselineWorkplaceIds,
            'baselineCurrentWorkplaceId' => $baselineCurrentWorkplaceId,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.actions.init.js?v=' . time(),
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
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getPersonalList();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getDataInactivos()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getPersonalInactivosList();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    /** Read-only, actor- and station-bound preview of missing canonical locality memberships. */
    public function previewChecadorMembershipBaseline(): void
    {
        $actorId = $this->requireChecadorBaselineEditor();
        $input = Request::all();
        $workplaceId = filter_var($input['workplace_id'] ?? null, FILTER_VALIDATE_INT);
        if ($workplaceId === false || $workplaceId === null || $workplaceId <= 0) {
            JsonResponse::validation('Selecciona una estación autorizada.');
        }

        try {
            $this->requireSelectedBaselineStation((int) $workplaceId);
            if (array_key_exists('selected_worker_ids', $input)
                || array_key_exists('full_candidate_fingerprint', $input)) {
                JsonResponse::validation('La vista previa de carga inicial incluye el conjunto elegible completo; no acepta selección parcial.');
            }
            $validFromUtc = $this->baselineCutoff((string) ($input['valid_from_utc'] ?? ''));
            $reason = is_scalar($input['reason'] ?? null) ? trim((string) $input['reason']) : '';
            if ($reason === '' || mb_strlen($reason) > 500) {
                JsonResponse::validation('Escribe el motivo RH para la incorporación, con un máximo de 500 caracteres.');
            }
            $previewService = new WorkerWorkplaceAssignmentBaselinePreviewService();
            $previewService->assertAuthorizedWorkplace((int) $workplaceId, $actorId);
            $previewService->prepareVersionMetadata($validFromUtc, $actorId, $reason);
            $preview = $previewService->preview((int) $workplaceId);
            $fullCandidateIds = array_values(array_map('intval', $preview['candidate_worker_ids'] ?? []));
            sort($fullCandidateIds, SORT_NUMERIC);
            $availableCandidateWorkers = is_array($preview['candidate_workers'] ?? null)
                ? $preview['candidate_workers']
                : [];
            $candidateCount = count($availableCandidateWorkers);
            $candidateFingerprint = (string) ($preview['candidate_snapshot_sha256'] ?? '');
            $snapshotReady = (int) ($preview['counts']['conflicting_assignments'] ?? 1) === 0
                && (int) ($preview['counts']['invalid_locality_references'] ?? 1) === 0
                && (int) ($preview['counts']['other_status_workers'] ?? 1) === 0
                && $candidateCount > 0;
            $ready = $snapshotReady;

            Session::set(self::CHECADOR_BASELINE_PREVIEW_SESSION_KEY, [
                'actor_id' => $actorId,
                'created_at' => time(),
                'ready_to_import' => $ready,
                'candidate_count' => $candidateCount,
                'candidate_fingerprint' => $candidateFingerprint,
                'source_fingerprint' => (string) ($preview['source_snapshot_sha256'] ?? ''),
                'workplace_id' => (int) $workplaceId,
                'valid_from_utc' => $validFromUtc,
                'reason' => $reason,
            ]);

            JsonResponse::send([
                'success' => true,
                'message' => $ready
                    ? 'Revisa el conjunto completo de la localidad, las exclusiones, el motivo RH y el corte UTC. No se han registrado adscripciones.'
                    : 'La vista previa tiene datos que requieren conciliación RH; la importación está bloqueada.',
                'data' => [
                    'candidate_worker_ids' => $fullCandidateIds,
                    'candidate_workers' => $availableCandidateWorkers,
                    'candidate_count' => $candidateCount,
                    'candidate_fingerprint' => $candidateFingerprint,
                    'source_fingerprint' => (string) ($preview['source_snapshot_sha256'] ?? ''),
                    'workplace_id' => (int) $workplaceId,
                    'recorded_by' => $actorId,
                    'locality_reference' => $preview['locality_reference'] ?? [],
                    'valid_from_utc' => $validFromUtc,
                    'reason' => $reason,
                    'active_worker_count' => (int) ($preview['counts']['active_workers'] ?? 0),
                    'active_worker_ids' => $preview['active_worker_ids'] ?? [],
                    'active_workers_with_membership_ids' => $preview['active_workers_with_membership_ids'] ?? [],
                    'existing_version_rows' => (int) ($preview['counts']['existing_version_rows'] ?? 0),
                    'counts' => $preview['counts'],
                    'locality_distribution' => $preview['locality_distribution'] ?? [],
                    'excluded_candidates_by_reason' => $preview['excluded_candidates_by_reason'] ?? [],
                    'ready_to_import' => $ready,
                    'import_enabled' => WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled(),
                    'requires_roster_confirmation' => true,
                    'conflicting_assignments' => (int) ($preview['counts']['conflicting_assignments'] ?? 0),
                    'database_writes' => false,
                ],
            ], 200, ['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache', 'Vary' => 'Cookie']);
        } catch (\DomainException $exception) {
            if (str_contains($exception->getMessage(), 'cutoff must be prospective')) {
                JsonResponse::validation('El corte UTC debe ser posterior al momento actual.');
            }
            JsonResponse::forbidden('No se pudo validar el permiso RH y el alcance de la localidad seleccionada.');
        } catch (\InvalidArgumentException) {
            JsonResponse::validation('El corte debe ser una fecha UTC válida y futura.');
        } catch (\Throwable) {
            JsonResponse::serverError('No fue posible generar la vista previa de membresías.');
        }
    }

    /** Import only the exact, recent, actor-bound preview confirmed by the RH editor. */
    public function importChecadorMembershipBaseline(): void
    {
        $actorId = $this->requireChecadorBaselineEditor();
        if (!WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled()) {
            JsonResponse::forbidden('La incorporación requiere el gate de preproducción y una conexión de BD explícitamente verificada.');
        }
        $input = Request::all();
        $workplaceId = filter_var($input['workplace_id'] ?? null, FILTER_VALIDATE_INT);
        $candidateCount = filter_var($input['candidate_count'] ?? null, FILTER_VALIDATE_INT);
        $candidateFingerprint = strtolower(trim((string) ($input['candidate_fingerprint'] ?? '')));
        $sourceFingerprint = strtolower(trim((string) ($input['source_fingerprint'] ?? '')));
        $validFromUtc = trim((string) ($input['valid_from_utc'] ?? ''));
        $confirmRoster = filter_var($input['confirm_roster'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sessionPreview = Session::get(self::CHECADOR_BASELINE_PREVIEW_SESSION_KEY, []);
        if (array_key_exists('selected_worker_ids', $input) || array_key_exists('full_candidate_fingerprint', $input)) {
            JsonResponse::validation('La importación utiliza el conjunto completo del preview vigente y no acepta IDs seleccionados.');
        }

        if ($workplaceId === false || $workplaceId === null || $workplaceId <= 0
            || $candidateCount === false || $candidateCount === null || $candidateCount <= 0
            || !preg_match('/\A[a-f0-9]{64}\z/D', $candidateFingerprint)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $sourceFingerprint)
            || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $validFromUtc)) {
            JsonResponse::validation('Los parámetros no coinciden con la autorización vigente.');
        }

        if (!is_array($sessionPreview)
            || (int) ($sessionPreview['actor_id'] ?? 0) !== $actorId
            || (int) ($sessionPreview['workplace_id'] ?? 0) !== (int) $workplaceId
            || (string) ($sessionPreview['valid_from_utc'] ?? '') !== $validFromUtc
            || (int) ($sessionPreview['candidate_count'] ?? 0) !== (int) $candidateCount
            || ($sessionPreview['ready_to_import'] ?? false) !== true
            || time() - (int) ($sessionPreview['created_at'] ?? 0) > 900
            || !hash_equals((string) ($sessionPreview['candidate_fingerprint'] ?? ''), $candidateFingerprint)
            || !hash_equals((string) ($sessionPreview['source_fingerprint'] ?? ''), $sourceFingerprint)
            || trim((string) ($sessionPreview['reason'] ?? '')) === '') {
            JsonResponse::conflict('La vista previa expiró o cambió. Genera una nueva antes de importar.');
        }

        if (!$confirmRoster) {
            JsonResponse::validation('Confirma que revisaste el conjunto completo, sus exclusiones, el motivo RH y el corte UTC del preview.');
        }

        try {
            $this->requireSelectedBaselineStation((int) $workplaceId);
            $reason = (string) ($sessionPreview['reason'] ?? '');
            $result = (new WorkerWorkplaceAssignmentBaselineImportService())->importFromPreview(
                (int) $workplaceId,
                (int) $candidateCount,
                $candidateFingerprint,
                $sourceFingerprint,
                $validFromUtc,
                $actorId,
                $reason,
                static function (array $result): void {}
            );
            JsonResponse::send([
                'success' => true,
                'message' => ($result['already_applied'] ?? false)
                    ? 'Las membresías de esta vista previa ya están registradas y verificadas.'
                    : 'Las membresías autorizadas se registraron y verificaron correctamente.',
                'data' => [
                    'inserted' => (int) ($result['inserted'] ?? 0),
                    'already_applied' => (bool) ($result['already_applied'] ?? false),
                    'candidate_count' => (int) ($result['candidate_count'] ?? 0),
                    'candidate_fingerprint' => (string) ($result['candidate_snapshot_sha256'] ?? ''),
                    'workplace_id' => (int) $workplaceId,
                    'valid_from_utc' => $validFromUtc,
                    'recorded_by' => $actorId,
                ],
            ], 200, ['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache', 'Vary' => 'Cookie']);
        } catch (\DomainException) {
            JsonResponse::conflict('La fuente RH, el conjunto aprobado o el corte cambió; no se confirmó la importación. Genera una nueva vista previa.');
        } catch (\Throwable) {
            JsonResponse::serverError('No fue posible completar la importación. El estado no se confirma; vuelve a generar la vista previa y verifica el historial.');
        }
    }

    private function requireChecadorBaselineEditor(): int
    {
        if (!Auth::check() || Auth::user() === null) {
            JsonResponse::unauthorized('Inicia sesión con una cuenta autorizada de Recursos Humanos.');
        }
        if (!ModuleStationService::isAvailable('control-documentos-personal')) {
            JsonResponse::forbidden('No tienes acceso a Control de documentos del personal.');
        }
        $permissions = ControlDocumentosPersonalService::getPermisos();
        $actorId = (int) ($permissions['id_usuario'] ?? 0);
        if ($actorId <= 0 || $actorId !== (int) Auth::id() || empty($permissions['puedeEditar'])) {
            JsonResponse::forbidden('La operación requiere permiso efectivo de edición RH.');
        }
        return $actorId;
    }

    private function requireSelectedBaselineStation(int $workplaceId): void
    {
        $context = ModuleStationService::getContext('control-documentos-personal');
        if (($context['id_depto'] ?? null) !== null
            || (int) ($context['id_estacion'] ?? 0) !== $workplaceId) {
            JsonResponse::forbidden('Selecciona una estación autorizada para incorporar membresías.');
        }
    }

    private function baselineCutoff(string $requestedCutoff): string
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $requestedCutoff)) {
            throw new \InvalidArgumentException('An explicit UTC baseline cutoff is required.');
        }
        return $requestedCutoff;
    }

    public function add()
    {
        header('Content-Type: application/json');
        $idempotencyKey = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');
        try {
            $result = ControlDocumentosPersonalService::addPersonal($_POST, $_FILES, $idempotencyKey);
        } catch (RhDirectCreateIdempotencyConflict) {
            http_response_code(409);
            echo json_encode(['success' => false, 'code' => 'idempotency_conflict', 'message' => 'Esta clave ya corresponde a otra alta. Recupera la solicitud original antes de iniciar una nueva.']);
            return;
        } catch (RhDirectCreateAuthorizationException) {
            http_response_code(403);
            echo json_encode(['success' => false, 'code' => 'forbidden', 'message' => 'No tienes autorización para dar de alta personal en esa localidad.']);
            return;
        } catch (\InvalidArgumentException) {
            http_response_code(422);
            echo json_encode(['success' => false, 'code' => 'invalid_request', 'message' => 'La solicitud de alta no es válida.']);
            return;
        } catch (\DomainException) {
            http_response_code(403);
            echo json_encode(['success' => false, 'code' => 'forbidden', 'message' => 'No se pudo validar la autorización del actor.']);
            return;
        } catch (\Throwable) {
            http_response_code(500);
            echo json_encode(['success' => false, 'code' => 'create_failed', 'message' => 'No fue posible completar el alta.']);
            return;
        }

        $id = $result['worker_id'] ?? null;
        $replayed = (bool)($result['idempotent_replay'] ?? false);
        http_response_code($id ? ($replayed ? 200 : 201) : 422);
        echo json_encode([
            'success' => (bool)$id,
            'worker_id' => $id ? (int)$id : null,
            'idempotent_replay' => $replayed,
            'message' => $id ? ($replayed ? 'El alta ya estaba registrada.' : 'Personal agregado correctamente.') : 'No fue posible registrar el alta.',
        ]);
        return;
    }

    public function edit()
    {
        header('Content-Type: application/json');
        $id = sanitize_input($_POST['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $updated = ControlDocumentosPersonalService::editPersonal($id, $_POST, $_FILES);
        echo json_encode([
            'success' => $updated,
            'message' => $updated ? 'Información actualizada correctamente.' : 'Error al actualizar.',
        ]);
        return;
    }

    public function delete()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = sanitize_input($data['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deletePersonal($id);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Personal eliminado correctamente.' : 'Error al eliminar.',
        ]);
        return;
    }

    public function getArchivosBaja()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_GET['id_baja'] ?? null, 'int');
        if (!$idBaja) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getArchivosBaja($idBaja);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function uploadBajaArchivo()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_POST['id_baja'] ?? null, 'int');
        $descripcion = sanitize_input($_POST['descripcion'] ?? null, 'string');

        if (!$idBaja || empty($descripcion)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'Archivo no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::uploadBajaArchivo($idBaja, $descripcion, $_FILES['archivo']);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Archivo subido correctamente.' : 'Error al subir archivo.',
        ]);
        return;
    }

    public function deleteBajaArchivo()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = sanitize_input($data['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deleteBajaArchivo($id);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Archivo eliminado correctamente.' : 'Error al eliminar archivo.',
        ]);
        return;
    }

    public function getComentariosBaja()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_GET['id_baja'] ?? null, 'int');
        if (!$idBaja) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getComentariosBaja($idBaja);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function addComentarioBaja()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idBaja = sanitize_input($data['id_baja'] ?? null, 'int');
        $comentario = sanitize_input($data['comentario'] ?? null, 'string');

        if (!$idBaja || empty($comentario)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addComentarioBaja($idBaja, $comentario);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Comentario agregado.' : 'Error al agregar comentario.',
        ]);
        return;
    }

    public function getComentarios()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');
        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getComentarios($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function addComentario()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id_personal'] ?? null, 'int');
        $comentario = sanitize_input($data['comentario'] ?? null, 'string');

        if (!$idPersonal || empty($comentario)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addComentario($idPersonal, $comentario);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Comentario agregado.' : 'Error al agregar comentario.',
        ]);
        return;
    }

    public function uploadDocumento()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_POST['id_personal'] ?? null, 'int');
        $tipo = sanitize_input($_POST['tipo'] ?? null, 'string');

        if (!$idPersonal || empty($tipo)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
            return;
        }

        if (empty($_FILES['archivo'])) {
            echo json_encode(['success' => false, 'message' => 'Archivo no proporcionado.']);
            return;
        }

        $uploaded = ControlDocumentosPersonalService::uploadDocumento($idPersonal, $tipo, $_FILES['archivo']);
        echo json_encode([
            'success' => $uploaded,
            'message' => $uploaded ? 'Documento subido correctamente.' : 'Error al subir documento.',
        ]);
        return;
    }

    public function deleteDocumento()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id'] ?? null, 'int');
        $tipo = sanitize_input($data['tipo'] ?? ($_GET['tipo'] ?? ''), 'string');

        if (!$idPersonal || empty($tipo)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deleteDocumento($idPersonal, $tipo);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Documento eliminado.' : 'Error al eliminar.',
        ]);
        return;
    }

    public function addBaja()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_POST['id_personal'] ?? null, 'int');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addBajaPersonal($idPersonal, $_POST, $_FILES);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Baja registrada correctamente.' : 'Error al registrar baja.',
        ]);
        return;
    }

    public function getAcceso()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'ID no proporcionado.']);
            return;
        }

        $data = ControlDocumentosPersonalService::getAcceso($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function editarPin()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id_personal'] ?? null, 'int');
        $pin = sanitize_input($data['pin'] ?? null, 'string');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::editarPin($idPersonal, $pin);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
        return;
    }

    public function getPuestos()
    {
        header('Content-Type: application/json');
        $puestos = RhPuestos::where('status', 1)
            ->orderBy('puesto')
            ->get(['id', 'puesto']);
        echo json_encode(['success' => true, 'data' => $puestos->toArray()]);
        return;
    }

    public function getEstaciones()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getEstaciones();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getPersonalById()
    {
        header('Content-Type: application/json');
        $id = sanitize_input($_GET['id'] ?? null, 'int');
        if (!$id) {
            echo json_encode(['success' => false, 'data' => null]);
            return;
        }
        $data = ControlDocumentosPersonalService::getPersonalById($id);
        echo json_encode(['success' => (bool)$data, 'data' => $data]);
        return;
    }

    public function excel()
    {
        PersonalExcelService::generarYDescargar();
    }

    public function kpi($idYear)
    {

        /*
$permisos = ControlDocumentosPersonalService::getPermisos();
if (!$permisos['puedeLeer']) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
*/

        $validados = DropdownYearMesService::validarYearMes($idYear, 1);
        $idYear = $validados['idYear'];

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1), '');

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/kpi-personal', [
            'title'    => 'Evaluación Personal (KPI\'s), ' . $idYear,
            'idYear'   => $idYear,
            'opciones' => KpiPersonalService::getOpciones(),
            'help'     => false,
            'moduleStationKey' => 'control-documentos-personal',
            'yearMesTemplate' => '/departamento-operativo/recursos-humanos/control-documentos-personal-kpi/{year}',
            'scripts'  => [
                '/assets/libs/apexcharts/dist/apexcharts.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/kpi-personal.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function kpiData($idYear, $tipo)
    {
        try {
            header('Content-Type: application/json');

            $validados = DropdownYearMesService::validarYearMes($idYear, 1);
            $idYear = $validados['idYear'];

            $tipo = (int) $tipo;
            if ($tipo < 1 || $tipo > 2) {
                echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
                return;
            }

            $ctx = ModuleStationService::getContext('control-documentos-personal');
            $idEstacion = $ctx['id_estacion'] !== null ? (int) $ctx['id_estacion'] : null;
            $idDepto = $ctx['id_depto'] !== null ? (int) $ctx['id_depto'] : null;

            if ($idEstacion === null && $idDepto !== null) {
                $idEstacion = $idDepto;
            }

            $data = KpiPersonalService::getData($idEstacion, $idYear, $tipo);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            error_log('Error KPI personal data: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error al cargar datos: ' . $e->getMessage()]);
        }
    }

    public function asistencia($id)
    {
        $idPersonal = (int)$id;
        if (!$idPersonal) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $data = ControlDocumentosPersonalService::getAsistenciaPersonal($idPersonal);
        if (!$data) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $title = 'Asistencia (' . $data['nombre_completo'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add($title);

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/asistencia', [
            'title'    => $title,
            'personal' => $data,
            'help'     => false,
            'scripts'  => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.asistencia.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getAsistenciaData()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');
        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getAsistenciaData($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function crearIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $incidencia   = sanitize_input($_POST['incidencia'] ?? null, 'string');
        $comentario   = sanitize_input($_POST['comentario'] ?? null, 'string');
        $documento    = !empty($_POST['documento']) ? sanitize_input($_POST['documento'], 'string') : null;

        if (!$idAsistencia || !$incidencia) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::crearIncidencia($idAsistencia, $incidencia, $comentario, $documento);
        echo json_encode($result);
        return;
    }

    public function agregarIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia         = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $idIncidenciaCatalogo = sanitize_input($_POST['id_incidencia'] ?? null, 'int');
        $comentario           = sanitize_input($_POST['comentario'] ?? null, 'string');
        $fechaInicio          = !empty($_POST['fecha_inicio']) ? sanitize_input($_POST['fecha_inicio'], 'string') : null;
        $fechaFin             = !empty($_POST['fecha_fin']) ? sanitize_input($_POST['fecha_fin'], 'string') : null;
        $sueldoDia            = isset($_POST['sueldo_dia']) && $_POST['sueldo_dia'] !== '' ? sanitize_input($_POST['sueldo_dia'], 'float') : null;

        if (!$idAsistencia || !$idIncidenciaCatalogo) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        $documentoRuta = null;
        if (!empty($_FILES['documento']) && is_array($_FILES['documento']) && !empty($_FILES['documento']['tmp_name']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $carpeta = ControlDocumentosPersonalService::getUploadDir() . 'incidencias/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
            $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
            $nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
                $documentoRuta = $nombre;
            }
        }

        $result = ControlDocumentosPersonalService::agregarIncidencia($idAsistencia, $idIncidenciaCatalogo, $comentario, $fechaInicio, $fechaFin, $sueldoDia, $documentoRuta);
        echo json_encode($result);
        return;
    }

    public function subirDocumentoIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $fechaInicio  = !empty($_POST['fecha_inicio']) ? sanitize_input($_POST['fecha_inicio'], 'string') : null;
        $fechaFin     = !empty($_POST['fecha_fin']) ? sanitize_input($_POST['fecha_fin'], 'string') : null;
        $sueldoDia    = isset($_POST['sueldo_dia']) && $_POST['sueldo_dia'] !== '' ? sanitize_input($_POST['sueldo_dia'], 'float') : null;

        if (!$idAsistencia) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        if (empty($_FILES['documento']) || !is_array($_FILES['documento']) || empty($_FILES['documento']['tmp_name']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        $carpeta = ControlDocumentosPersonalService::getUploadDir() . 'incidencias/';
        if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
        $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
        $nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;
        $documentoRuta = null;

        if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
            $documentoRuta = $nombre;
        }

        if (!$documentoRuta) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        $result = ControlDocumentosPersonalService::subirDocumentoIncidencia($idAsistencia, $documentoRuta, $fechaInicio, $fechaFin, $sueldoDia);
        echo json_encode($result);
        return;
    }

    public function getIncidenciasCatalogo()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getIncidenciasCatalogo();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getIncidenciaPorAsistencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_GET['id_asistencia'] ?? null, 'int');
        if (!$idAsistencia) {
            echo json_encode(['success' => false, 'data' => null]);
            return;
        }
        $data = ControlDocumentosPersonalService::getIncidenciaPorAsistencia($idAsistencia);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function acceso($id)
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Funcionalidad en desarrollo.']);
        return;
    }

    public function bajaDetalle($id)
    {
        $idBaja = (int)$id;
        if (!$idBaja) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $data = ControlDocumentosPersonalService::getBajaDetalle($idBaja);
        if (!$data) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $title = 'Detalle Baja de Personal (' . $data['nombre_completo'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add($title);

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/detalle-baja', [
            'title'  => $title,
            'baja'   => $data,
            'help'   => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.detalle-baja.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }
}
