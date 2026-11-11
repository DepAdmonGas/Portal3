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

        createModal.addEventListener('shown.bs.modal', () => {
            initModalSelects(createModal);
            if (!$?.fn?.select2 || $(workerSelect).hasClass('select2-hidden-accessible')) return;
            $(workerSelect).select2({
                dropdownParent: $(createModal),
                width: '100%',
                placeholder: 'Busca por nombre, ID o número',
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    noResults: () => 'No hay comodines elegibles. Verifica la adscripción habitual registrada por RH, el periodo y las localidades autorizadas.',
                    searching: () => 'Buscando…',
                    inputTooShort: () => 'Busca por nombre, ID RH o número de colaborador.',
                },
                ajax: {
                    url: form.dataset.workersUrl,
                    dataType: 'json',
                    delay: 250,
                    cache: false,
                    data: (params) => ({
                        q: params.term || '',
                        fecha_desde_local: from.value,
                        fecha_hasta_local_exclusiva: to.value,
                    }),
                    processResults: (payload) => {
                        const workers = (Array.isArray(payload.workers) ? payload.workers : [])
                            .filter((worker) => worker.eligible === true
                                && Number.isInteger(Number(worker.id_home_membership_version))
                                && Number(worker.id_home_membership_version) > 0
                                && Number.isInteger(Number(worker.home_workplace_id))
                                && Number(worker.home_workplace_id) > 0)
                            .map((worker) => {
                            const name = String(worker.full_name || 'Nombre no disponible');
                            const employeeNumber = worker.employee_number === null || worker.employee_number === undefined
                                ? ''
                                : ' · No. ' + String(worker.employee_number);
                            return {
                                id: String(worker.worker_id),
                                text: name + employeeNumber + ' · Elegible',
                                eligible: true,
                                membershipId: Number(worker.id_home_membership_version) || 0,
                                homeWorkplaceId: Number(worker.home_workplace_id) || 0,
                            };
                        });
                        if (payload.has_more) message(workerMessage, 'Hay más resultados elegibles; afina la búsqueda por nombre, ID RH o número de colaborador.');
                        else if (workers.length === 0) message(workerMessage, payload.eligibility_notice || 'No hay comodines elegibles. Verifica la adscripción habitual registrada por RH, el periodo y las localidades autorizadas.');
                        else message(workerMessage, workers.length + ' trabajador(es) elegible(s) para el periodo.');
                        return { results: workers, pagination: { more: false } };
                    },
                    error: (xhr) => {
                        const payload = xhr.responseJSON || {};
                        message(workerMessage, payload.message || 'No se pudo consultar el origen de candidatos.', true);
                    },
                },
            });
            $(workerSelect).on('select2:select.comodines', (event) => {
                const selected = event.params?.data || {};
                if (selected.eligible !== true) {
                    $(workerSelect).val(null).trigger('change');
                    membershipId.value = '';
                    updateHomeWorkplace('');
                    message(workerMessage, selected.eligibilityMessage || 'El trabajador no es elegible para este periodo.', true);
                    return;
                }
                membershipId.value = String(selected.membershipId || '');
                updateHomeWorkplace(selected.homeWorkplaceId);
                message(workerMessage, 'Origen y membresía verificados para el periodo.');
            }).on('select2:clear.comodines', () => {
                membershipId.value = '';
                updateHomeWorkplace('');
            }).on('select2:opening.comodines', (event) => {
                if (!from.value || !to.value || to.value <= from.value) {
                    event.preventDefault();
                    message(workerMessage, 'Selecciona primero un periodo válido para buscar trabajadores.');
                }
            });
        });

        [from, to].forEach((field) => field.addEventListener('change', () => {
            membershipId.value = '';
            updateHomeWorkplace('');
            targetSelect.value = '';
            $(targetSelect).trigger('change');
            if ($?.fn?.select2 && $(workerSelect).hasClass('select2-hidden-accessible')) {
                $(workerSelect).val(null).trigger('change');
            }
            if (!from.value || !to.value || to.value <= from.value) {
                message(workerMessage, 'Selecciona primero un periodo válido para buscar trabajadores.');
            } else {
                message(workerMessage, 'El periodo cambió. Busca de nuevo para verificar la elegibilidad.');
            }
        }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const worker = Number(workerSelect.value);
            const membership = Number(membershipId.value);
            const home = Number(homeWorkplaceId.value);
            const target = Number(targetSelect.value);
            const reason = document.getElementById('checador-comodines-reason').value.trim();
            const days = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
            const schedule = days.map((day) => {
                const value = document.querySelector(`[data-coverage-day="${day}"]`)?.value || '';
                if (value === 'DAY_OFF') return { dia_semana: day, tipo: 'DAY_OFF', id_plantilla: null, version_plantilla: null };
                const [templateId, version] = value.split(':').map(Number);
                return { dia_semana: day, tipo: 'WORK', id_plantilla: templateId || null, version_plantilla: version || null };
            });
            if (!worker || !membership || !home || !target || target === home
                || !from.value || !to.value || to.value <= from.value || !reason || reason.length > 500
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
            submit.disabled = true;
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
                submit.disabled = false;
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
