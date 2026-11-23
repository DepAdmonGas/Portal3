document.addEventListener('DOMContentLoaded', () => {
    const $ = window.jQuery;
    const safeJson = async (response) => {
        try { return await response.json(); } catch (_error) { return {}; }
    };
    const makeKey = () => window.crypto?.randomUUID
        ? window.crypto.randomUUID()
        : Array.from(window.crypto.getRandomValues(new Uint8Array(24)), (byte) => byte.toString(16).padStart(2, '0')).join('');
    const message = (node, text, error = false) => {
        if (!node) return;
        node.textContent = text;
        node.classList.toggle('d-none', !text);
        node.classList.toggle('alert-danger', error);
        node.classList.toggle('alert-info', !error);
    };
    const initModalSelects = (modal) => {
        if (!$?.fn?.select2 || !modal) return;
        $(modal).find('select.select2-modal-field, .select2-modal-field select:not([data-select2-ajax])').each((_index, select) => {
            const $select = $(select);
            if ($select.hasClass('select2-hidden-accessible')) return;
            $select.select2({
                dropdownParent: $(modal),
                width: '100%',
                allowClear: !select.required,
                language: {
                    noResults: () => 'No hay resultados.',
                    searching: () => 'Buscando…',
                    inputTooShort: () => 'Escribe al menos dos caracteres.',
                },
            });
        });
    };
    const bindModalSelects = (modal) => {
        if (!modal || modal.dataset.select2Bound === '1') return;
        modal.addEventListener('shown.bs.modal', () => initModalSelects(modal));
        modal.addEventListener('hidden.bs.modal', () => {
            if (!$?.fn?.select2) return;
            $(modal).find('select.select2-hidden-accessible').each((_index, select) => $(select).select2('destroy'));
        });
        modal.dataset.select2Bound = '1';
    };

    const filterModal = document.getElementById('checador-comodines-search-modal');
    bindModalSelects(filterModal);

    const form = document.getElementById('checador-comodines-create-form');
    const createModal = document.getElementById('checador-comodines-create-modal');
    if (form && createModal) {
        bindModalSelects(createModal);
        const workerSelect = document.getElementById('checador-comodines-worker');
        const membershipId = document.getElementById('checador-comodines-membership-id');
        const homeWorkplaceId = document.getElementById('checador-comodines-home-workplace-id');
        const targetSelect = document.getElementById('checador-comodines-target');
        const workerMessage = document.getElementById('checador-comodines-worker-message');
        const responseMessage = document.getElementById('checador-comodines-create-message');
        const from = document.getElementById('checador-comodines-from');
        const to = document.getElementById('checador-comodines-to');
        const submit = form.querySelector('[data-checador-comodines-submit]');
        let previousPayload = '';
        let requestKey = '';
        let selectedWorkerId = 0;
        let eligibilityVerified = false;
        let eligibilityRequest = null;
        let eligibilitySequence = 0;
        let submitting = false;
        submit.disabled = true;
        const isValidDate = (value) => {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return false;
            const date = new Date(value + 'T00:00:00.000Z');
            return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
        };
        const hasValidPeriod = () => isValidDate(from.value) && isValidDate(to.value) && to.value > from.value;
        const coverageSchedule = () => ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'].map((day) => {
            const value = document.querySelector(`[data-coverage-day="${day}"]`)?.value || '';
            if (value === 'DAY_OFF') return { dia_semana: day, tipo: 'DAY_OFF', id_plantilla: null, version_plantilla: null };
            const [templateId, version] = value.split(':').map(Number);
            return { dia_semana: day, tipo: 'WORK', id_plantilla: templateId || null, version_plantilla: version || null };
        });
        const otherRequestFieldsValid = () => {
            const worker = Number(workerSelect.value);
            const membership = Number(membershipId.value);
            const home = Number(homeWorkplaceId.value);
            const target = Number(targetSelect.value);
            const reason = document.getElementById('checador-comodines-reason').value.trim();
            const schedule = coverageSchedule();
            return eligibilityVerified && worker > 0 && worker === selectedWorkerId
                && membership > 0 && home > 0 && target > 0 && target !== home
                && hasValidPeriod() && reason.length > 0 && reason.length <= 500
                && schedule.every((day) => day.tipo === 'DAY_OFF' || (day.id_plantilla > 0 && day.version_plantilla > 0));
        };
        const refreshSubmitState = () => {
            submit.disabled = submitting || !otherRequestFieldsValid();
        };
        form.addEventListener('input', refreshSubmitState);
        form.addEventListener('change', refreshSubmitState);
        const updateHomeWorkplace = (value) => {
            const homeId = Number(value) || 0;
            homeWorkplaceId.value = homeId > 0 ? String(homeId) : '';
            Array.from(targetSelect.options).forEach((option) => {
                option.disabled = homeId > 0 && Number(option.value) === homeId;
            });
            if (homeId > 0 && Number(targetSelect.value) === homeId) {
                targetSelect.value = '';
                $(targetSelect).trigger('change');
            }
        };
        const eligibilityReasonText = (reason) => ({
            coverage_period_required: 'Selecciona las fechas para comprobar la adscripción del trabajador.',
            candidate_membership_not_valid_for_period: 'Trabajador encontrado, pero sin adscripción canónica válida para este periodo.',
            candidate_membership_outside_authorized_scope: 'La adscripción canónica queda fuera del alcance autorizado.',
            candidate_membership_unverified: 'No se pudo verificar la adscripción canónica.',
        }[String(reason || '')] || 'No se pudo confirmar la elegibilidad para este periodo.');
        const invalidateEligibility = () => {
            eligibilitySequence++;
            if (eligibilityRequest && typeof eligibilityRequest.abort === 'function') eligibilityRequest.abort();
            eligibilityRequest = null;
            eligibilityVerified = false;
            membershipId.value = '';
            updateHomeWorkplace('');
            refreshSubmitState();
        };
        const verifySelectedWorker = () => {
            const sequence = ++eligibilitySequence;
            if (eligibilityRequest && typeof eligibilityRequest.abort === 'function') eligibilityRequest.abort();
            eligibilityRequest = null;
            eligibilityVerified = false;
            membershipId.value = '';
            updateHomeWorkplace('');
            refreshSubmitState();
            if (!selectedWorkerId) return;
            if (!from.value && !to.value) {
                message(workerMessage, 'Trabajador encontrado. Captura el periodo para comprobar su adscripción canónica.');
                return;
            }
            if (!hasValidPeriod()) {
                message(workerMessage, 'Trabajador encontrado. Completa ambas fechas con un rango válido para comprobar la adscripción.');
                return;
            }
            message(workerMessage, 'Verificando adscripción canónica para el periodo…');
            const workerId = selectedWorkerId;
            const rangeFrom = from.value;
            const rangeTo = to.value;
            eligibilityRequest = $.ajax({
                url: form.dataset.workersUrl,
                method: 'GET',
                dataType: 'json',
                cache: false,
                data: { q: String(workerId), fecha_desde_local: rangeFrom, fecha_hasta_local_exclusiva: rangeTo },
            }).done((payload) => {
                if (sequence !== eligibilitySequence || selectedWorkerId !== workerId || from.value !== rangeFrom || to.value !== rangeTo) return;
                const candidate = (Array.isArray(payload.workers) ? payload.workers : [])
                    .find((worker) => Number(worker.worker_id) === workerId);
                const hasMembership = candidate
                    && candidate.eligible === true
                    && Number(candidate.id_home_membership_version) > 0
                    && Number(candidate.home_workplace_id) > 0;
                if (!hasMembership) {
                    message(workerMessage, candidate
                        ? eligibilityReasonText(candidate.eligibility_reason)
                        : 'Trabajador encontrado, pero no se pudo confirmar dentro del alcance autorizado para este periodo.', true);
                    return;
                }
                membershipId.value = String(Number(candidate.id_home_membership_version));
                updateHomeWorkplace(Number(candidate.home_workplace_id));
                eligibilityVerified = true;
                refreshSubmitState();
                message(workerMessage, 'Adscripción canónica y periodo verificados.');
            }).fail((xhr, status) => {
                if (status === 'abort' || sequence !== eligibilitySequence) return;
                const payload = xhr.responseJSON || {};
                message(workerMessage, payload.message || 'No se pudo verificar la adscripción en MariaDB. Intenta de nuevo.', true);
            }).always(() => {
                if (sequence === eligibilitySequence) eligibilityRequest = null;
            });
        };

        createModal.addEventListener('shown.bs.modal', () => {
            message(workerMessage, '');
            message(responseMessage, '');
            initModalSelects(createModal);
            if (!$?.fn?.select2) return;
            if (!$(workerSelect).hasClass('select2-hidden-accessible')) $(workerSelect).select2({
                dropdownParent: $(createModal),
                width: '100%',
                placeholder: 'Busca por nombre, ID o número',
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    noResults: () => 'No se encontraron trabajadores puesto 14 dentro de las localidades autorizadas.',
                    errorLoading: () => 'No se pudo consultar el padrón autorizado. Revisa la conexión e intenta de nuevo.',
                    searching: () => 'Buscando…',
                },
                ajax: {
                    url: form.dataset.workersUrl,
                    dataType: 'json',
                    delay: 250,
                    cache: false,
                    data: (params) => {
                        message(workerMessage, '');
                        const data = { q: params.term || '', page: params.page || 1 };
                        if (hasValidPeriod()) {
                            data.fecha_desde_local = from.value;
                            data.fecha_hasta_local_exclusiva = to.value;
                        }
                        return data;
                    },
                    processResults: (payload) => {
                        const reasonText = (reason) => ({
                            candidate_membership_not_valid_for_period: 'Sin adscripción canónica válida para todo el periodo',
                            candidate_membership_outside_authorized_scope: 'Adscripción fuera del alcance autorizado',
                            candidate_membership_unverified: 'No se pudo verificar la adscripción canónica',
                        }[String(reason || '')] || 'No cumple la membresía canónica requerida');
                        const workers = (Array.isArray(payload.workers) ? payload.workers : []).map((worker) => {
                            const name = String(worker.full_name || 'Nombre no disponible');
                            const employeeNumber = worker.employee_number === null || worker.employee_number === undefined
                                ? ''
                                : ' · No. ' + String(worker.employee_number);
                            const workerId = Number(worker.worker_id) || 0;
                            const hasMembership = Number.isInteger(Number(worker.id_home_membership_version))
                                && Number(worker.id_home_membership_version) > 0
                                && Number.isInteger(Number(worker.home_workplace_id))
                                && Number(worker.home_workplace_id) > 0;
                            const eligible = worker.eligible === true && hasMembership;
                            const reason = String(worker.eligibility_reason || '');
                            const eligibilityMessage = eligible ? '' : reason === 'coverage_period_required'
                                ? 'Pendiente de seleccionar fechas'
                                : reasonText(reason);
                            return {
                                id: String(workerId),
                                text: name + ' · ID ' + workerId + employeeNumber + (eligible ? ' · Elegible' : ' · ' + eligibilityMessage),
                                disabled: worker.selection_allowed !== true,
                                eligible,
                                selectionAllowed: worker.selection_allowed === true,
                                eligibilityReason: reason,
                                eligibilityMessage,
                                membershipId: eligible ? Number(worker.id_home_membership_version) : 0,
                                homeWorkplaceId: eligible ? Number(worker.home_workplace_id) : 0,
                            };
                        });
                        const foundCount = Number(payload.candidate_count ?? workers.length);
                        const eligibleCount = Number(payload.eligible_count ?? workers.filter((worker) => worker.eligible).length);
                        if (foundCount === 0) message(workerMessage, payload.eligibility_notice || 'No se encontraron trabajadores puesto 14 dentro de las localidades autorizadas.');
                        else if (!hasValidPeriod()) message(workerMessage, foundCount + ' candidato(s) encontrados. Las fechas están pendientes o incompletas; la elegibilidad se verificará al completar el periodo.');
                        else if (eligibleCount === 0) message(workerMessage, foundCount + ' comodín(es) encontrado(s); revisa el motivo de adscripción junto a cada nombre. No se habilita el guardado hasta confirmarla.', true);
                        else if (payload.has_more) message(workerMessage, foundCount + ' encontrado(s); ' + eligibleCount + ' elegible(s) en estos resultados. Afina la búsqueda por nombre o ID para más coincidencias.');
                        else message(workerMessage, foundCount + ' encontrado(s); ' + eligibleCount + ' elegible(s) para el periodo.');
                        return { results: workers, pagination: { more: Boolean(payload.has_more) } };
                    },
                    error: (xhr) => {
                        const payload = xhr.responseJSON || {};
                        message(workerMessage, payload.message || 'No se pudo consultar el origen de candidatos.', true);
                    },
                },
            });
            $(workerSelect).off('.comodines').on('select2:select.comodines', (event) => {
                const selected = event.params?.data || {};
                if (selected.selectionAllowed !== true) {
                    $(workerSelect).val(null).trigger('change');
                    selectedWorkerId = 0;
                    invalidateEligibility();
                    message(workerMessage, selected.eligibilityMessage || 'El trabajador no es elegible para este periodo.', true);
                    return;
                }
                selectedWorkerId = Number(selected.id) || 0;
                verifySelectedWorker();
            }).on('select2:clear.comodines', () => {
                selectedWorkerId = 0;
                invalidateEligibility();
                message(workerMessage, '');
            }).on('select2:opening.comodines', (event) => {
                message(workerMessage, '');
            });
        });

        [from, to].forEach((field) => field.addEventListener('change', () => {
            invalidateEligibility();
            targetSelect.value = '';
            $(targetSelect).trigger('change');
            if (!hasValidPeriod()) {
                if (selectedWorkerId) message(workerMessage, 'La elegibilidad anterior fue invalidada. Completa ambas fechas con un rango válido para volver a verificar.');
                else message(workerMessage, 'Captura un rango válido; la búsqueda de trabajadores no requiere fechas.');
            } else {
                if (selectedWorkerId) verifySelectedWorker();
                else message(workerMessage, 'El periodo está listo. Busca y selecciona un trabajador para verificar su adscripción.');
            }
        }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const worker = Number(workerSelect.value);
            const membership = Number(membershipId.value);
            const home = Number(homeWorkplaceId.value);
            const target = Number(targetSelect.value);
            const reason = document.getElementById('checador-comodines-reason').value.trim();
            const schedule = coverageSchedule();
            if (!eligibilityVerified || !membership || !home) {
                message(responseMessage, 'No se puede guardar: verifica primero una adscripción canónica válida para el trabajador y el periodo.', true);
                return;
            }
            if (!otherRequestFieldsValid() || !worker || !target || target === home
                || !reason || reason.length > 500
                || schedule.some((day) => day.tipo === 'WORK' && (!day.id_plantilla || !day.version_plantilla))) {
                message(responseMessage, 'Completa trabajador, localidad, vigencia, motivo y los siete días del patrón.', true);
                return;
            }
            const requestBody = {
                id_personal: worker,
                id_home_workplace: home,
                id_home_membership_version: membership,
                id_coverage_workplace: target,
                fecha_desde_local: from.value,
                fecha_hasta_local_exclusiva: to.value,
                timezone_iana: 'America/Mexico_City',
                proposed_weekly_schedule: schedule,
                motivo_solicitud: reason,
            };
            const payloadSignature = JSON.stringify(requestBody);
            if (!requestKey || payloadSignature !== previousPayload) {
                previousPayload = payloadSignature;
                requestKey = `coverage-request:${makeKey()}`;
            }
            submitting = true;
            refreshSubmitState();
            try {
                const response = await fetch(form.dataset.createUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('checador-comodines-csrf').value,
                        'Idempotency-Key': requestKey,
                    },
                    body: payloadSignature,
                });
                const payload = await safeJson(response);
                if (!response.ok || payload.success !== true) {
                    message(responseMessage, payload.message || 'Portal3 no confirmó la solicitud; no se confirmó ningún cambio.', true);
                    return;
                }
                responseMessage.className = 'alert alert-success mt-3 mb-0';
                message(responseMessage, 'Solicitud registrada y pendiente de aprobación.');
                requestKey = '';
                previousPayload = '';
                window.bootstrap?.Modal.getOrCreateInstance(createModal).hide();
                window.setTimeout(() => window.location.reload(), 500);
            } catch (_error) {
                message(responseMessage, 'No se pudo confirmar el resultado. Actualiza la lista antes de reintentar.', true);
            } finally {
                submitting = false;
                refreshSubmitState();
            }
        });
    }

    const detailModal = document.getElementById('checador-comodines-detail-modal');
    const transitionModal = document.getElementById('checador-comodines-transition-modal');
    let transitionSignature = '';
    let transitionKey = '';
    document.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const detailButton = target?.closest('[data-coverage-detail]');
        if (detailButton && detailModal) {
            const status = detailModal.querySelector('[data-coverage-detail-message]');
            const content = detailModal.querySelector('[data-coverage-detail-content]');
            content.replaceChildren();
            message(status, 'Consultando detalle e historial…');
            window.bootstrap?.Modal.getOrCreateInstance(detailModal).show();
            try {
                const response = await fetch(detailButton.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const payload = await safeJson(response);
                if (!response.ok || payload.success !== true || !payload.data) throw new Error(payload.message || 'No se pudo consultar el detalle.');
                const data = payload.data;
                message(status, '');
                const rows = [
                    ['Estado', data.status],
                    ['Trabajador', data.presentation?.worker_label || 'No disponible'],
                    ['Localidad habitual', data.presentation?.home_label || 'No disponible'],
                    ['Localidad de cobertura', data.presentation?.coverage_label || 'No disponible'],
                    ['Desde (incluido)', data.fecha_desde_local],
                    ['Hasta (sin incluir)', data.fecha_hasta_local_exclusiva],
                    ['Zona horaria', data.timezone_iana],
                    ['Motivo', data.motivo_solicitud],
                    ['Motivo de transición', data.motivo_transicion || 'No aplica'],
                    ['Patrón semanal', (data.proposed_weekly_schedule || []).map((day) => `${day.dia_semana}: ${day.tipo === 'DAY_OFF' ? 'Descanso explícito' : `Plantilla ${day.id_plantilla}, versión ${day.version_plantilla}`}`).join(' · ')],
                    ['Historial', (data.revision_history || []).map((revision) => `Rev. ${revision.revision_no}: ${revision.status} · ${revision.transition_action} · ${revision.recorded_at_utc}`).join(' | ') || 'Sin revisiones disponibles'],
                ];
                rows.forEach(([label, value]) => {
                    const dt = document.createElement('dt');
                    dt.className = 'col-sm-4';
                    dt.textContent = label;
                    const dd = document.createElement('dd');
                    dd.className = 'col-sm-8 text-break';
                    dd.textContent = String(value ?? 'No disponible');
                    content.append(dt, dd);
                });
            } catch (error) {
                message(status, error.message || 'No se pudo consultar el historial.', true);
            }
            return;
        }

        const action = target?.closest('[data-coverage-transition]');
        const transition = document.getElementById('checador-comodines-transition-form');
        if (action && transition && transitionModal) {
            transition.querySelector('[data-coverage-transition-uuid]').value = action.dataset.uuid || '';
            transition.querySelector('[data-coverage-transition-action]').value = action.dataset.coverageTransition || '';
            document.getElementById('checador-comodines-transition-heading').textContent = action.dataset.coverageTransition === 'authorize' ? 'Aprobar cobertura' : 'Cancelar cobertura';
            document.getElementById('checador-comodines-transition-reason').value = '';
            message(transition.querySelector('[data-coverage-transition-message]'), '');
            transition.querySelector('[data-coverage-transition-submit]').textContent = action.dataset.coverageTransition === 'authorize' ? 'Aprobar' : 'Cancelar cobertura';
            window.bootstrap?.Modal.getOrCreateInstance(transitionModal).show();
        }
    });

    const transition = document.getElementById('checador-comodines-transition-form');
    transition?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const uuid = transition.querySelector('[data-coverage-transition-uuid]').value;
        const action = transition.querySelector('[data-coverage-transition-action]').value;
        const reason = document.getElementById('checador-comodines-transition-reason').value.trim();
        const status = transition.querySelector('[data-coverage-transition-message]');
        const submit = transition.querySelector('[data-coverage-transition-submit]');
        if (!uuid || !['authorize', 'cancel'].includes(action) || !reason) {
            message(status, 'Indica un motivo para continuar.', true);
            return;
        }
        const signature = JSON.stringify({ uuid, action, reason });
        if (!transitionKey || signature !== transitionSignature) {
            transitionSignature = signature;
            transitionKey = `coverage-${action}:${makeKey()}`;
        }
        submit.disabled = true;
        try {
            const response = await fetch(`${transition.dataset.baseUrl}/${encodeURIComponent(uuid)}/${action}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': transition.dataset.csrf,
                    'Idempotency-Key': transitionKey,
                },
                body: JSON.stringify({ motivo: reason }),
            });
            const payload = await safeJson(response);
            if (!response.ok || payload.success !== true) {
                message(status, payload.message || 'Portal3 no confirmó la transición; el estado no cambió.', true);
                return;
            }
            message(status, action === 'authorize' ? 'Cobertura autorizada. Actualizando…' : 'Cobertura cancelada. Actualizando…');
            transitionKey = '';
            transitionSignature = '';
            window.bootstrap?.Modal.getOrCreateInstance(transitionModal).hide();
            window.setTimeout(() => window.location.reload(), 500);
        } catch (_error) {
            message(status, 'No se pudo confirmar el resultado. Actualiza la lista antes de reintentar.', true);
        } finally {
            submit.disabled = false;
        }
    });
});
