(function () {
    'use strict';

    let activeFilters = { worker_id: '', category: '', status: '', start_date: '', end_date: '' };
    let dataTable = null;
    let latestRequest = 0;
    let closeSearchAfterDraw = false;
    let hasSuccessfulResponse = false;
    let lastSuccessfulResponse = null;

    document.addEventListener('alpine:init', function () {
        Alpine.data('m06IncidenceFilters', function () {
            return {
                filters: { worker_id: '', category: '', status: '', start_date: '', end_date: '' },
                cargando: false,
                init: function () {
                    window.addEventListener('m06-incidences:loading', (event) => {
                        this.cargando = Boolean(event.detail && event.detail.loading);
                    });
                },
                buscar: function () {
                    const form = document.querySelector('[data-m06-filter-form]');
                    if (form) {
                        const values = new FormData(form);
                        this.filters = {
                            worker_id: values.get('worker_id') || '',
                            category: values.get('category') || '',
                            status: values.get('status') || '',
                            start_date: values.get('start_date') || '',
                            end_date: values.get('end_date') || ''
                        };
                    }
                    this.cargando = true;
                    window.dispatchEvent(new CustomEvent('m06-incidences:search', {
                        detail: Object.assign({}, this.filters)
                    }));
                },
                limpiar: function () {
                    this.filters = { worker_id: '', category: '', status: '', start_date: '', end_date: '' };
                    const worker = document.getElementById('m06-filter-worker');
                    if (worker && window.jQuery) window.jQuery(worker).val('').trigger('change');
                    this.buscar();
                }
            };
        });
    });

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
        });
    }

    function setListState(message, kind, preserveRows) {
        const state = document.querySelector('[data-m06-list-state]');
        const wrapper = document.querySelector('[data-m06-table-wrapper]');
        if (!state) return;
        state.textContent = message || '';
        state.hidden = !message;
        state.classList.toggle('alert', kind === 'error');
        state.classList.toggle('alert-danger', kind === 'error');
        state.classList.toggle('text-body-secondary', kind !== 'error');
        state.classList.toggle('text-center', kind !== 'error');
        state.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        if (wrapper) wrapper.hidden = (kind === 'error' && !preserveRows)
            || (kind !== 'error' && message === 'No hay incidencias registradas para los filtros seleccionados');
    }

    function categoryLabel(code) {
        return ({
            VACATION: 'Vacaciones', PERMISSION: 'Permiso', DISABILITY: 'Incapacidad',
            AUTHORIZED_LATENESS: 'Retardo autorizado',
            AUTHORIZED_EARLY_DEPARTURE: 'Salida anticipada autorizada', OFFICIAL_MISSION: 'Misión oficial'
        })[code] || 'Tipo no disponible';
    }

    function statusLabel(code) {
        return ({
            PENDING_APPROVAL: 'Pendiente de autorización',
            RESOLVED_PENDING_AUTHORIZATION: 'Resuelta · pendiente de autorización',
            ACTIVE: 'Activa', CANCELLED: 'Cancelada', SUPERSEDED: 'Sustituida',
            REVIEW_REQUIRED: 'Requiere revisión', REJECTED: 'Rechazada', REVOKED: 'Revocada'
        })[code] || 'Estado no disponible';
    }

    function renderActions(row, table) {
        const uuid = String(row.uuid_incidencia || '');
        if (!/^[0-9a-f-]{36}$/i.test(uuid)) return '';
        const workplaceId = Number(row.workplace_id) || 0;
        const revision = Number(row.current_revision) || 0;
        const status = String(row.current_status || '');
        const terminal = ['CANCELLED', 'REVOKED', 'REJECTED'].includes(status);
        const canRevise = table.dataset.m06CanRevise === '1' && !terminal;
        const canResolve = table.dataset.m06CanResolve === '1' && !['CANCELLED', 'REVOKED'].includes(status);
        const canAuthorize = table.dataset.m06CanAuthorize === '1' && !['CANCELLED', 'REVOKED', 'REJECTED'].includes(status);
        const canReject = table.dataset.m06CanReject === '1' && !['CANCELLED', 'REVOKED', 'REJECTED'].includes(status);
        const canRevoke = table.dataset.m06CanRevoke === '1' && !['CANCELLED', 'REVOKED'].includes(status);
        const base = '/departamento-operativo/recursos-humanos/checador/incidencias/' + encodeURIComponent(uuid);
        let html = '<div class="dropdown"><button class="btn p-0 border-0 bg-transparent text-body" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones para ' + escapeHtml(row.worker_name || 'trabajador') + '"><i class="ti ti-dots-vertical fs-5" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end shadow-sm">';
        html += '<li><button type="button" class="dropdown-item" data-m06-incidence-view="detail"><i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle</button></li>';
        html += '<li><button type="button" class="dropdown-item" data-m06-incidence-view="history"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver historial</button></li>';
        if (canRevise || canResolve || canAuthorize || canReject || canRevoke) html += '<li><hr class="dropdown-divider"></li>';
        function lifecycle(action, endpoint, label, icon, dates) {
            return '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="' + action + '" data-m06-action-url="' + base + '/' + endpoint + '" data-m06-current-revision="' + revision + '" data-m06-workplace="' + workplaceId + '"' + (dates ? ' data-m06-start="' + escapeHtml(row.start_date || '') + '" data-m06-end="' + escapeHtml(row.end_date || '') + '"' : '') + '><i class="ti ' + icon + ' me-1" aria-hidden="true"></i>' + label + '</button></li>';
        }
        if (canRevise) html += lifecycle('revision', 'revisiones', 'Crear revisión', 'ti-edit', true);
        if (canResolve) html += lifecycle('resolve', 'resolver', 'Resolver', 'ti-check', false);
        if (canAuthorize) html += lifecycle('authorize', 'autorizar', 'Autorizar', 'ti-shield-check', false);
        if (canReject) html += lifecycle('reject', 'rechazar', 'Rechazar', 'ti-ban', false);
        if (canRevoke) html += lifecycle('revoke', 'revocar', 'Revocar', 'ti-arrow-back-up', false);
        html += '</ul></div>';
        return html;
    }

    function renderHistory(row) {
        const list = document.querySelector('[data-m06-history-list]');
        const empty = document.querySelector('[data-m06-history-empty]');
        if (!list || !empty) return;
        list.replaceChildren();
        const revisions = Array.isArray(row.revisions) ? row.revisions : [];
        empty.hidden = revisions.length > 0;
        revisions.forEach(function (revision) {
            const item = document.createElement('li');
            item.className = 'list-group-item px-0';
            const heading = document.createElement('div');
            heading.className = 'd-flex flex-wrap justify-content-between gap-2';
            const title = document.createElement('strong');
            title.textContent = 'Revisión ' + (Number(revision.revision_number) || 0) + ' · ' + statusLabel(revision.status);
            const created = document.createElement('span');
            created.className = 'small text-muted';
            created.textContent = (revision.created_at_utc || 'Fecha no disponible') + ' UTC';
            heading.append(title, created);
            item.append(heading);
            const addLine = function (label, value, muted) {
                const line = document.createElement('div');
                line.className = 'small mt-1' + (muted ? ' text-muted' : '');
                const strong = document.createElement('strong');
                strong.textContent = label + ': ';
                line.append(strong, document.createTextNode(value || 'No disponible'));
                item.append(line);
            };
            addLine('Registró', Number(revision.created_by_user_id) > 0 ? 'Usuario ' + Number(revision.created_by_user_id) : 'Actor no disponible');
            addLine('Motivo', revision.reason || 'No disponible');
            addLine('Evidencia', revision.evidence_reference || 'No disponible', true);
            addLine('Referencia', row.uuid_incidencia || 'No disponible', true);
            if (revision.approved_by_user_id !== null && revision.approved_by_user_id !== undefined) {
                addLine('Actor de decisión', 'Usuario ' + Number(revision.approved_by_user_id), true);
            }
            list.append(item);
        });
        const count = document.querySelector('[data-m06-history-count]');
        if (count) count.textContent = String(revisions.length);
    }

    function showIncidence(row, tabName) {
        const modal = document.getElementById('m06-incidence-detail-modal');
        if (!modal || !row) return;
        const setText = function (selector, value) {
            const target = modal.querySelector(selector);
            if (target) target.textContent = value || 'No disponible';
        };
        setText('[data-m06-detail-heading]', 'Incidencia · ' + categoryLabel(row.category));
        setText('[data-m06-detail-worker]', (row.worker_name || 'Nombre no disponible') + ' · ID ' + (Number(row.worker_id) || 0));
        setText('[data-m06-detail-workplace]', (document.querySelector('[data-checador-m06-incidences]')?.dataset.m06WorkplaceName || 'No disponible') + ' · ' + (Number(row.workplace_id) || 0));
        setText('[data-m06-detail-category]', categoryLabel(row.category));
        setText('[data-m06-detail-period]', (row.start_date || 'No disponible') + ' — ' + (row.end_date || 'No disponible'));
        setText('[data-m06-detail-status]', statusLabel(row.current_status));
        setText('[data-m06-detail-reason]', row.reason);
        setText('[data-m06-detail-evidence]', row.evidence_reference);
        setText('[data-m06-detail-created]', row.created_at_utc);
        setText('[data-m06-detail-uuid]', row.uuid_incidencia);
        renderHistory(row);
        modal.addEventListener('shown.bs.modal', function () {
            const target = document.getElementById(tabName === 'history' ? 'm06-incidence-history-tab' : 'm06-incidence-detail-tab');
            if (target) window.bootstrap.Tab.getOrCreateInstance(target).show();
        }, { once: true });
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function initializeWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;
        const $ = window.jQuery;
        modal.querySelectorAll('[data-m06-worker-select]').forEach(function (select) {
            const $select = $(select);
            if (!$select.hasClass('select2-hidden-accessible')) {
                const isFilter = select.getAttribute('data-m06-worker-select') === 'filter';
                $select.select2({
                    width: '100%', language: 'es', minimumResultsForSearch: 0,
                    dropdownParent: $(modal), placeholder: isFilter ? 'Todos' : 'Selecciona un trabajador',
                    allowClear: isFilter
                });
            }
            $select.css('width', '100%');
            $select.next('.select2-container').css('width', '100%');
        });
    }

    function destroyWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;
        modal.querySelectorAll('[data-m06-worker-select]').forEach(function (select) {
            const $select = window.jQuery(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.off('.m06IncidenceSelect2');
                $select.select2('destroy');
            }
        });
    }

    function bindModalWorkerSelect2(modal) {
        if (!modal || modal.dataset.m06Select2Bound === '1') return;
        modal.dataset.m06Select2Bound = '1';
        modal.addEventListener('shown.bs.modal', function () { initializeWorkerSelect2(modal); });
        modal.addEventListener('hidden.bs.modal', function () { destroyWorkerSelect2(modal); });
        if (modal.classList.contains('show')) initializeWorkerSelect2(modal);
    }

    function initializeM06Incidences() {
        const table = document.querySelector('[data-checador-m06-incidences]');
        if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable
            && !window.jQuery.fn.DataTable.isDataTable(table)) {
            const $ = window.jQuery;
            const filterModal = document.getElementById('m06-incidence-search-modal');
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const lang = '/assets/libs/datatables.net/js/es-ES.json';
            dataTable = $(table).DataTable({
                processing: true,
                serverSide: true,
                stateSave: false,
                order: [[6, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: [1, 4, 7], orderable: false, searchable: false }],
                language: {
                    url: lang,
                    emptyTable: '', zeroRecords: '',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ incidencias',
                    infoEmpty: 'Sin incidencias',
                    infoFiltered: '(filtrado de _MAX_ incidencias)',
                    lengthMenu: 'Mostrar _MENU_ incidencias',
                    loadingRecords: 'Cargando…', processing: 'Procesando…',
                    search: 'Buscar incidencias:',
                    paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
                },
                columns: [
                    { data: 'worker_name', render: function (value, type, row) {
                        if (type !== 'display') return value;
                        const employeeNumber = row.employee_number ? ' · No. ' + row.employee_number : '';
                        return '<span class="fw-semibold">' + escapeHtml(value || 'Nombre no disponible') + '</span><span class="d-block small text-muted">ID ' + (Number(row.worker_id) || 0) + escapeHtml(employeeNumber) + '</span>';
                    } },
                    { data: null, render: function (_value, type) {
                        const name = table.dataset.m06WorkplaceName || 'Localidad no disponible';
                        return type === 'display' ? escapeHtml(name) : name;
                    } },
                    { data: 'category', render: function (value, type) { return type === 'display' ? escapeHtml(categoryLabel(value)) : value; } },
                    { data: 'start_date', render: function (value, type, row) { return type === 'display' ? '<span class="text-nowrap">' + escapeHtml(value) + ' — ' + escapeHtml(row.end_date) + '</span>' : value; } },
                    { data: 'reason', render: function (value, type) {
                        const reason = String(value || 'Sin motivo registrado');
                        if (type !== 'display') return reason;
                        const excerpt = reason.length > 160 ? reason.slice(0, 157) + '…' : reason;
                        return '<span class="text-break" title="' + escapeHtml(reason) + '">' + escapeHtml(excerpt) + '</span>';
                    } },
                    { data: 'current_status', render: function (value, type) { return type === 'display' ? '<span class="badge ' + (value === 'PENDING_APPROVAL' ? 'text-bg-warning' : 'text-bg-secondary') + '">' + escapeHtml(statusLabel(value)) + '</span>' : value; } },
                    { data: 'created_at_utc', render: function (value, type) { return type === 'display' ? '<span class="text-nowrap">' + escapeHtml(value || 'No disponible') + '</span>' : value; } },
                    { data: null, className: 'text-center', defaultContent: '', render: function (_value, type, row) { return type === 'display' ? renderActions(row, table) : ''; } }
                ],
                ajax: function (request, callback) {
                    const requestId = ++latestRequest;
                    const body = Object.assign({}, activeFilters, {
                        workplace_id: Number(table.dataset.m06WorkplaceId),
                        draw: request.draw, start: request.start, length: request.length,
                        search: request.search || { value: '', regex: false },
                        order: request.order || []
                    });
                    const headers = { 'Content-Type': 'application/json' };
                    if (tokenMeta && tokenMeta.content) headers['X-CSRF-TOKEN'] = tokenMeta.content;
                    window.dispatchEvent(new CustomEvent('m06-incidences:loading', { detail: { loading: true } }));
                    window.axios.post(table.dataset.m06DataUrl, body, { headers: headers }).then(function (response) {
                        const result = response.data || {};
                        if (requestId !== latestRequest || Number(result.draw) !== Number(request.draw)) return;
                        if (result.success !== true || !Array.isArray(result.data)) {
                            throw new Error(result.message || 'La respuesta de incidencias no tiene el formato esperado.');
                        }
                        hasSuccessfulResponse = true;
                        lastSuccessfulResponse = {
                            recordsTotal: Number(result.recordsTotal) || 0,
                            recordsFiltered: Number(result.recordsFiltered) || 0,
                            data: result.data.slice()
                        };
                        setListState(Number(result.recordsFiltered) === 0 ? 'No hay incidencias registradas para los filtros seleccionados' : '', '');
                        callback(result);
                        window.dispatchEvent(new CustomEvent('m06-incidences:loading', { detail: { loading: false } }));
                        if (closeSearchAfterDraw && filterModal && window.bootstrap) {
                            window.bootstrap.Modal.getOrCreateInstance(filterModal).hide();
                            closeSearchAfterDraw = false;
                        }
                    }).catch(function (error) {
                        if (requestId !== latestRequest) return;
                        const response = error && error.response;
                        const message = response && response.data && response.data.message
                            ? response.data.message
                            : (error && error.message && !response
                                ? error.message
                                : (response && response.status === 419
                                ? 'La sesión de seguridad venció. Actualiza la página y vuelve a consultar.'
                                : 'No fue posible consultar las incidencias. Intenta nuevamente.'));
                        const preserveRows = hasSuccessfulResponse && Array.isArray(lastSuccessfulResponse?.data)
                            && lastSuccessfulResponse.data.length > 0;
                        setListState(message + (preserveRows ? ' Se conservan los resultados de la consulta anterior.' : ''), 'error', preserveRows);
                        callback(Object.assign({ draw: request.draw }, lastSuccessfulResponse || {
                            recordsTotal: 0, recordsFiltered: 0, data: []
                        }));
                        window.dispatchEvent(new CustomEvent('m06-incidences:loading', { detail: { loading: false } }));
                        closeSearchAfterDraw = false;
                    });
                }
            });

            window.addEventListener('m06-incidences:search', function (event) {
                activeFilters = Object.assign({ worker_id: '', category: '', status: '', start_date: '', end_date: '' }, event.detail || {});
                closeSearchAfterDraw = true;
                if (dataTable) dataTable.ajax.reload(null, true);
            });
            $(table).on('click', '[data-m06-incidence-view]', function () {
                const row = dataTable.row($(this).closest('tr')).data();
                showIncidence(row, this.getAttribute('data-m06-incidence-view'));
            });
        }

        document.querySelectorAll('[data-m06-select2-modal]').forEach(bindModalWorkerSelect2);

        const actionModal = document.querySelector('[data-m06-action-modal]');
        if (actionModal && actionModal.dataset.m06Bound !== '1') {
            actionModal.dataset.m06Bound = '1';
            const form = actionModal.querySelector('[data-m06-action-form]');
            const reason = actionModal.querySelector('[data-m06-reason]');
            const reasonLabel = actionModal.querySelector('[data-m06-reason-label]');
            const dateFields = Array.from(actionModal.querySelectorAll('[data-m06-revision-date]'));
            const dateInputs = dateFields.map(function (field) { return field.querySelector('input'); }).filter(Boolean);
            const actionTitle = actionModal.querySelector('[data-m06-action-title]');
            const confirmLabel = actionModal.querySelector('[data-m06-confirm-label]');
            const submitLabel = actionModal.querySelector('[data-m06-submit-label]');
            const reasonConfig = {
                revision: ['reason', 'Motivo de la revisión', true, 'Crear revisión'],
                resolve: ['resolution_notes', 'Notas de resolución', true, 'Resolver incidencia'],
                authorize: ['action_notes', 'Notas de autorización (opcional)', false, 'Autorizar incidencia'],
                reject: ['rejection_reason', 'Motivo del rechazo', true, 'Rechazar incidencia'],
                revoke: ['revocation_reason', 'Motivo de la revocación', true, 'Revocar incidencia']
            };
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-m06-open-action]');
                if (!trigger || !form || !reason) return;
                const action = trigger.getAttribute('data-m06-action') || '';
                const endpoint = trigger.getAttribute('data-m06-action-url') || '';
                const config = reasonConfig[action];
                if (!config || !/^\/departamento-operativo\/recursos-humanos\/checador\/incidencias\/[0-9a-f-]{36}\/(revisiones|resolver|autorizar|rechazar|revocar)$/i.test(endpoint)) {
                    form.action = '';
                    return;
                }
                form.action = endpoint;
                form.querySelector('[name="workplace_id"]').value = trigger.getAttribute('data-m06-workplace') || '';
                form.querySelector('[name="expected_current_revision"]').value = trigger.getAttribute('data-m06-current-revision') || '';
                reason.name = config[0];
                reason.value = '';
                reason.required = config[2];
                reasonLabel.textContent = config[1];
                const revisionAction = action === 'revision';
                dateFields.forEach(function (field) { field.hidden = !revisionAction; });
                dateInputs.forEach(function (input, index) {
                    input.disabled = !revisionAction;
                    input.value = revisionAction ? (trigger.getAttribute(index === 0 ? 'data-m06-start' : 'data-m06-end') || '') : '';
                });
                if (actionTitle) actionTitle.textContent = config[3];
                if (submitLabel) submitLabel.textContent = config[3];
                if (confirmLabel) confirmLabel.textContent = 'Confirmo ' + config[3].toLowerCase() + ' para la revisión ' + (trigger.getAttribute('data-m06-current-revision') || '') + '.';
                const confirm = form.querySelector('[name="confirm_action"]');
                if (confirm) confirm.checked = false;
            });
            actionModal.addEventListener('hidden.bs.modal', function () {
                if (form) form.reset();
                if (form) form.action = '';
            });
        }

        document.querySelectorAll('[data-m06-auto-open="1"]').forEach(function (modal) {
            if (window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    }

    document.addEventListener('m06-incidences:search', function (event) {
        activeFilters = Object.assign({ worker_id: '', category: '', status: '', start_date: '', end_date: '' }, event.detail || {});
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeM06Incidences, { once: true });
    } else {
        initializeM06Incidences();
    }
}());
