<div id="container" class="mt-4 mb-5">
    <?php
    $returnQuery = [];
    if (($selectedDate ?? '') !== '') {
        $returnQuery['date'] = (string) $selectedDate;
    }
    if (($selectedWorkerId ?? null) !== null) {
        $returnQuery['worker_id'] = (int) $selectedWorkerId;
    }
    $returnHref = '/departamento-operativo/recursos-humanos/checador/asistencia'
        . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
    $configurationHref = '/departamento-operativo/recursos-humanos/checador/configuracion';
    $catalogStatus = (string) ($templateCatalogStatus ?? 'unavailable');
    $csrfToken = '';
    try {
        if (session_status() === PHP_SESSION_ACTIVE || isset($_SESSION)) {
            $csrfToken = (string) \App\Core\CsrfToken::token();
        }
    } catch (\Throwable) {
        $csrfToken = '';
    }
    ?>

    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button
                type="button"
                class="btn btn-light dropdown-toggle text-dark"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
                aria-label="Opciones del Checador">
                <i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item pointer" href="<?= htmlspecialchars($configurationHref, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="ti ti-settings me-1" aria-hidden="true"></i>
                        Configuración
                    </a>
                </li>
                <li>
                    <a class="dropdown-item pointer" href="<?= htmlspecialchars($returnHref, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="ti ti-calendar-check me-1" aria-hidden="true"></i>
                        Asistencia del día
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-warning" role="status">
            <strong>Modo de demostración activo.</strong>
            No se muestran horarios operativos en esta consulta.
        </div>
    <?php endif; ?>
    <?php if (is_array($feedback ?? null)): ?><div class="alert alert-<?= htmlspecialchars(in_array(($feedback['type'] ?? ''), ['success', 'info'], true) ? (string) $feedback['type'] : 'warning', ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars((string) ($feedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="card mb-4" id="m04-template-catalog" aria-labelledby="m04-template-catalog-heading"
        x-data="m04TurnCreation()"
        data-save-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales"
        data-customize-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/personalizar-global"
        data-edit-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/versionar"
        data-edit-enabled="<?= !empty($canManageStationTemplates) ? '1' : '0' ?>"
        data-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>"
        data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clock fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="m04-template-catalog-heading">Turnos disponibles</h2>
                <span class="small text-white-50">Horarios de la estación</span>
            </div>
            <?php if (!empty($canManageStationTemplates)): ?>
                <button type="button" class="btn bg-white text-primary ms-auto" @click="openCreate()" id="m04-btn-new-template">
                    <i class="ti ti-plus me-1" aria-hidden="true"></i>Nuevo
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="alert alert-danger py-2 mb-3" role="alert" x-show="error" x-cloak x-text="error"></div>
            <div class="alert alert-warning py-2 mb-3" role="status" x-show="refreshWarning" x-cloak x-text="refreshWarning"></div>
            <div class="alert alert-success py-2 mb-3" role="status" x-show="success" x-cloak x-text="success"></div>

            <?php if ($catalogStatus === 'not_requested'): ?>
                <p class="mb-0 text-muted" role="status">Selecciona una localidad autorizada para consultar los horarios.</p>
            <?php elseif ($catalogStatus === 'unavailable'): ?>
                <p class="mb-0 text-warning-emphasis" role="status">No fue posible consultar los horarios. Intenta actualizar la página.</p>
            <?php elseif (($catalogStatus === 'partial') && ($scheduleTemplates ?? []) === []): ?>
                <p class="mb-0 text-warning-emphasis" role="status">La consulta no pudo confirmar los horarios disponibles.</p>
            <?php elseif (($scheduleTemplates ?? []) === [] && (empty($canManageStationTemplates) || $catalogStatus !== 'received')): ?>
                <p class="mb-0 text-muted" role="status">No hay horarios para mostrar en esta consulta.</p>
            <?php else: ?>
                <?php if ($catalogStatus === 'partial'): ?>
                    <p class="small text-warning-emphasis" role="status">La información de horarios puede estar incompleta.</p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0 w-100" id="m04-template-table">
                        <caption class="visually-hidden">Horarios disponibles para la localidad seleccionada</caption>
                        <thead><tr>
                            <th scope="col">Turno</th>
                            <th scope="col">Entrada</th>
                            <th scope="col">Inicio de descanso</th>
                            <th scope="col">Fin de descanso</th>
                            <th scope="col">Salida final</th>
                            <th scope="col">Estado</th>
                            <?php if (!empty($canManageStationTemplates)): ?>
                                <th scope="col" class="text-center text-nowrap" data-m04-actions-header aria-label="Acciones">
                                    <span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>
                                </th>
                            <?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($scheduleTemplates as $template): ?>
                            <?php
                                $segments = is_array($template['segmentos'] ?? null) ? $template['segmentos'] : [];
                                usort($segments, static fn(array $a, array $b): int => (int) ($a['ordinal'] ?? 0) <=> (int) ($b['ordinal'] ?? 0));
                                $workSegments = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) ($segment['tipo_segmento'] ?? '')) === 'WORK'));
                                $breakSegments = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) ($segment['tipo_segmento'] ?? '')) === 'BREAK'));
                                $endpointMarkup = static function (array $segment, string $timeKey, string $offsetKey): string {
                                    $time = substr((string) ($segment[$timeKey] ?? ''), 0, 5);
                                    $offset = (int) ($segment[$offsetKey] ?? 0);
                                    return '<span data-m04-time24="' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '" data-m04-day-offset="' . $offset . '">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>';
                                };
                                $rawName = trim((string) ($template['nombre'] ?? ''));
                                $displayTemplateName = preg_replace('/\ALegacy\s+[^—–-]+[—–-]\s*/iu', '', $rawName) ?? $rawName;
                                $displayTemplateName = preg_replace('/\s*(?:[—–-]\s*)?\b\d{1,2}:\d{2}(?::\d{2})?\s*[—–-]\s*\d{1,2}:\d{2}(?::\d{2})?(?:\s*\(\+?\d+\s*d[ií]a\))?\s*\z/u', '', $displayTemplateName) ?? $displayTemplateName;
                                $displayTemplateName = trim(preg_replace('/[—–-]\s*\z/u', '', $displayTemplateName));
                                if ($displayTemplateName === '' || strcasecmp($displayTemplateName, (string) ($template['clave'] ?? '')) === 0) {
                                    $displayTemplateName = $rawName !== '' ? $rawName : 'Turno';
                                }
                            ?>
                            <tr data-template-key="<?= htmlspecialchars((string) ($template['clave'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-template-version="<?= (int) ($template['version'] ?? 0) ?>">
                                <th scope="row"><?= htmlspecialchars($displayTemplateName, ENT_QUOTES, 'UTF-8') ?></th>
                                <td><?= $workSegments !== [] ? $endpointMarkup($workSegments[0], 'hora_inicio_local', 'dia_offset_inicio') : '—' ?></td>
                                <td><?= $breakSegments !== [] ? implode('<br>', array_map(static fn(array $segment): string => $endpointMarkup($segment, 'hora_inicio_local', 'dia_offset_inicio'), $breakSegments)) : '—' ?></td>
                                <td><?= $breakSegments !== [] ? implode('<br>', array_map(static fn(array $segment): string => $endpointMarkup($segment, 'hora_fin_local', 'dia_offset_fin'), $breakSegments)) : '—' ?></td>
                                <td><?= $workSegments !== [] ? $endpointMarkup($workSegments[array_key_last($workSegments)], 'hora_fin_local', 'dia_offset_fin') : '—' ?></td>
                                <td><?= (int) ($template['es_activo'] ?? 0) === 1 ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-secondary">Inactiva</span>' ?></td>
                                <?php if (!empty($canManageStationTemplates)): ?>
                                    <td class="text-center">
                                        <?php if (strtoupper((string) ($template['scope'] ?? '')) === 'GLOBAL' && is_array($template['customize_fields'] ?? null)): ?>
                                            <?php
                                                $existingCopy = is_array($template['station_customization']['template'] ?? null) ? $template['station_customization']['template'] : null;
                                                $copyFields = $existingCopy['edit_fields'] ?? $template['customize_fields'];
                                                $actionData = $existingCopy !== null
                                                    ? ['key' => (string) $existingCopy['clave'], 'version' => (int) $existingCopy['version'], 'name' => $displayTemplateName,
                                                        'type' => (string) ($existingCopy['tipo_turno'] ?? 'REGULAR'), 'fields' => $copyFields]
                                                    : ['key' => (string) $template['clave'], 'version' => (int) $template['version'], 'name' => $displayTemplateName,
                                                        'type' => (string) ($template['tipo_turno'] ?? 'REGULAR'), 'fields' => $template['customize_fields'],
                                                        'segments' => $segments];
                                                $actionJson = json_encode($actionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                            ?>
                                            <div class="dropdown dropstart">
                                                <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones del turno" aria-expanded="false" role="button" tabindex="0"><i class="ti ti-dots-vertical fs-6"></i></a>
                                                <ul class="dropdown-menu">
                                                    <?php if ($existingCopy !== null): ?>
                                                        <li><a class="dropdown-item pointer" href="#" data-m04-edit-template="<?= htmlspecialchars((string) $actionJson, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-edit"></i> Editar personalización</a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item pointer" href="#" data-m04-customize-template="<?= htmlspecialchars((string) $actionJson, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-copy"></i> Personalizar para mi estación</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php elseif (strtoupper((string) ($template['scope'] ?? '')) === 'STATION' && is_array($template['edit_fields'] ?? null)): ?>
                                            <?php
                                                $editData = ['key' => (string) $template['clave'], 'version' => (int) $template['version'],
                                                    'name' => $displayTemplateName, 'type' => (string) ($template['tipo_turno'] ?? 'REGULAR'),
                                                    'fields' => $template['edit_fields']];
                                                $editJson = json_encode($editData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                            ?>
                                            <div class="dropdown dropstart">
                                                <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones del turno" aria-expanded="false" role="button" tabindex="0">
                                                    <i class="ti ti-dots-vertical fs-6"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item pointer" href="#" data-m04-edit-template="<?= htmlspecialchars((string) $editJson, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="ti ti-edit"></i> Editar
                                                    </a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($canManageStationTemplates)): ?>
            <div class="modal fade" id="m04-create-template-modal" tabindex="-1" aria-labelledby="m04-create-template-modal-title" aria-hidden="true"
                data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-md modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h4 class="modal-title text-white" id="m04-create-template-modal-title">
                                <i class="ti ti-clock-plus me-2" aria-hidden="true"></i><span x-text="customizationSource ? 'Personalizar para mi estación' : 'Nuevo turno'"></span>
                            </h4>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="loading"></button>
                        </div>
                        <form @submit.prevent="save()" novalidate>
                            <div class="modal-body text-start">
                                <div class="alert alert-info py-2" role="status" x-show="customizationSource" x-cloak>
                                    Se creará una copia local de este turno. El horario global original y las asignaciones actuales permanecerán sin cambios.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="m04-turn-name">Nombre del turno <span class="text-danger">*</span></label>
                                    <input class="form-control" id="m04-turn-name" type="text" maxlength="128" required
                                        placeholder="Ej. Turno 4"
                                        x-model="name" @input="fieldErrors.name = ''; error = ''"
                                        :class="fieldErrors.name ? 'is-invalid' : ''"
                                        :disabled="loading" autocomplete="off">
                                    <div class="invalid-feedback" x-show="fieldErrors.name" x-text="fieldErrors.name"></div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label" for="m04-turn-start">Hora de entrada <span class="text-danger">*</span></label>
                                        <input class="form-control" id="m04-turn-start" type="time" lang="en-US" step="60" required
                                            x-model="startTime" @input="fieldErrors.startTime = ''; error = ''"
                                            :class="fieldErrors.startTime ? 'is-invalid' : ''"
                                            :disabled="loading">
                                        <div class="invalid-feedback" x-show="fieldErrors.startTime" x-text="fieldErrors.startTime"></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="m04-turn-end">Hora de salida final <span class="text-danger">*</span></label>
                                        <input class="form-control" id="m04-turn-end" type="time" lang="en-US" step="60" required
                                            x-model="endTime" @input="fieldErrors.endTime = ''; error = ''"
                                            :class="fieldErrors.endTime ? 'is-invalid' : ''"
                                            :disabled="loading">
                                        <div class="invalid-feedback" x-show="fieldErrors.endTime" x-text="fieldErrors.endTime"></div>
                                    </div>
                                </div>

                                <div class="border rounded p-3 mb-3 bg-light">
                                    <span class="form-label d-block text-dark fw-semibold mb-2">
                                        Descanso intermedio <span class="text-muted fw-normal small">(opcional)</span>
                                    </span>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label small text-muted" for="m04-turn-break-start">Inicio de descanso</label>
                                            <input class="form-control form-control-sm" id="m04-turn-break-start" type="time" lang="en-US" step="60"
                                                x-model="breakStart" @input="fieldErrors.break = ''; error = ''"
                                                :class="fieldErrors.break ? 'is-invalid' : ''"
                                                :disabled="loading">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted" for="m04-turn-break-end">Fin de descanso</label>
                                            <input class="form-control form-control-sm" id="m04-turn-break-end" type="time" lang="en-US" step="60"
                                                x-model="breakEnd" @input="fieldErrors.break = ''; error = ''"
                                                :class="fieldErrors.break ? 'is-invalid' : ''"
                                                :disabled="loading">
                                        </div>
                                    </div>
                                    <div class="invalid-feedback d-block" x-show="fieldErrors.break" x-text="fieldErrors.break"></div>
                                </div>

                                <div class="text-danger small mt-2" role="alert" x-show="error" x-text="error"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="loading">
                                    <i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <i class="ti ti-check me-1" aria-hidden="true"></i><span x-text="loading ? 'Guardando…' : 'Guardar turno'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($canManageStationTemplates)): ?>
            <div class="modal fade" id="m04-edit-template-modal" tabindex="-1" aria-labelledby="m04-edit-template-modal-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-md modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h4 class="modal-title text-white" id="m04-edit-template-modal-title"><i class="ti ti-edit me-2" aria-hidden="true"></i>Editar turno</h4>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="editLoading"></button>
                        </div>
                        <form @submit.prevent="saveEdit()" novalidate>
                            <div class="modal-body text-start">
                                <div class="mb-3">
                                    <label class="form-label" for="m04-edit-turn-name">Nombre del turno <span class="text-danger">*</span></label>
                                    <input class="form-control" id="m04-edit-turn-name" type="text" maxlength="128" required x-model="editName" @input="editFieldErrors.name = ''; editError = ''" :class="editFieldErrors.name ? 'is-invalid' : ''" :disabled="editLoading" autocomplete="off">
                                    <div class="invalid-feedback" x-show="editFieldErrors.name" x-text="editFieldErrors.name"></div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6"><label class="form-label" for="m04-edit-turn-start">Hora de entrada <span class="text-danger">*</span></label><input class="form-control" id="m04-edit-turn-start" type="time" lang="en-US" step="60" required x-model="editStartTime" @input="editFieldErrors.startTime = ''; editError = ''" :class="editFieldErrors.startTime ? 'is-invalid' : ''" :disabled="editLoading"><div class="invalid-feedback" x-show="editFieldErrors.startTime" x-text="editFieldErrors.startTime"></div></div>
                                    <div class="col-6"><label class="form-label" for="m04-edit-turn-end">Hora de salida final <span class="text-danger">*</span></label><input class="form-control" id="m04-edit-turn-end" type="time" lang="en-US" step="60" required x-model="editEndTime" @input="editFieldErrors.endTime = ''; editError = ''" :class="editFieldErrors.endTime ? 'is-invalid' : ''" :disabled="editLoading"><div class="invalid-feedback" x-show="editFieldErrors.endTime" x-text="editFieldErrors.endTime"></div></div>
                                </div>
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <span class="form-label d-block text-dark fw-semibold mb-2">Descanso intermedio <span class="text-muted fw-normal small">(opcional)</span></span>
                                    <div class="row g-3">
                                        <div class="col-6"><label class="form-label small text-muted" for="m04-edit-turn-break-start">Inicio de descanso</label><input class="form-control form-control-sm" id="m04-edit-turn-break-start" type="time" lang="en-US" step="60" x-model="editBreakStart" @input="editFieldErrors.break = ''; editError = ''" :class="editFieldErrors.break ? 'is-invalid' : ''" :disabled="editLoading"></div>
                                        <div class="col-6"><label class="form-label small text-muted" for="m04-edit-turn-break-end">Fin de descanso</label><input class="form-control form-control-sm" id="m04-edit-turn-break-end" type="time" lang="en-US" step="60" x-model="editBreakEnd" @input="editFieldErrors.break = ''; editError = ''" :class="editFieldErrors.break ? 'is-invalid' : ''" :disabled="editLoading"></div>
                                    </div>
                                    <div class="invalid-feedback d-block" x-show="editFieldErrors.break" x-text="editFieldErrors.break"></div>
                                </div>
                                <div class="small text-muted mb-2" x-show="editVersion">Se creará una versión nueva; el historial y las asignaciones anteriores se conservan.</div>
                                <div class="text-danger small mt-2" role="alert" x-show="editError" x-text="editError"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="editLoading"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button>
                                <button type="submit" class="btn btn-primary" :disabled="editLoading"><i class="ti ti-check me-1" aria-hidden="true"></i><span x-text="editLoading ? 'Guardando…' : 'Guardar cambios'"></span></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
