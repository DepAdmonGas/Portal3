(() => {
    'use strict';

    function formatVisibleTime(timeValue, dayOffset = 0) {
        const match = String(timeValue || '').match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
        if (!match) return String(timeValue || '');
        const hour = Number(match[1]);
        const minute = Number(match[2]);
        if (hour > 23 || minute > 59) return String(timeValue);
        const suffix = hour < 12 ? 'am' : 'pm';
        const displayHour = String((hour % 12) || 12).padStart(2, '0');
        const offset = Number(dayOffset) || 0;
        return `${displayHour}:${match[2]} ${suffix}` + (offset > 0 ? ` (+${offset} día)` : '');
    }

    function formatTableTimes(root) {
        if (!root) return;
        root.querySelectorAll('[data-m04-time24]').forEach((node) => {
            const rawTime = node.dataset.m04Time24 || '';
            const dayOffset = Number(node.dataset.m04DayOffset || 0);
            node.textContent = formatVisibleTime(rawTime, dayOffset);
            const cell = node.closest('td');
            if (cell && !cell.hasAttribute('data-order')) {
                const match = rawTime.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
                if (match) cell.setAttribute('data-order', String(dayOffset * 1440 + Number(match[1]) * 60 + Number(match[2])));
            }
        });
    }

    function endpointMarkup(segment, timeKey, offsetKey, escapeHtml) {
        const rawTime = String(segment?.[timeKey] || '').substring(0, 5);
        const dayOffset = Number(segment?.[offsetKey] || 0);
        const escapedTime = escapeHtml(rawTime);
        return `<span data-m04-time24="${escapedTime}" data-m04-day-offset="${dayOffset}">${escapedTime}</span>`;
    }

    function initDataTable() {
        const table = document.getElementById('m04-template-table');
        if (!table) return null;
        formatTableTimes(table);
        if (!window.jQuery?.fn?.DataTable) return null;
        if (window.jQuery.fn.DataTable.isDataTable(table)) {
            return window.jQuery(table).DataTable();
        }

        const actionHeader = table.querySelector('thead [data-m04-actions-header]');
        const actionColumnIndex = actionHeader
            ? Array.from(actionHeader.parentElement.children).indexOf(actionHeader)
            : -1;

        return window.jQuery(table).DataTable({
            processing: false,
            serverSide: false,
            autoWidth: false,
            stateSave: false,
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: actionColumnIndex >= 0
                ? [{ targets: actionColumnIndex, orderable: false, searchable: false, className: 'text-center text-nowrap' }]
                : [],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDataTable();
    });

    document.addEventListener('alpine:init', () => {
        Alpine.data('m04TurnCreation', () => ({
            table: null,
            modal: null,
            editModal: null,
            rootElement: null,
            loading: false,
            editLoading: false,
            writeEnabled: false,
            writeBlockMessage: '',
            editRowNode: null,
            name: '',
            startTime: '',
            endTime: '',
            breakStart: '',
            breakEnd: '',
            fieldErrors: {
                name: '',
                startTime: '',
                endTime: '',
                break: '',
            },
            error: '',
            refreshWarning: '',
            success: '',
            editTemplate: null,
            customizationSource: null,
            editName: '',
            editStartTime: '',
            editEndTime: '',
            editBreakStart: '',
            editBreakEnd: '',
            editVersion: 0,
            editFieldErrors: { name: '', startTime: '', endTime: '', break: '' },
            editError: '',
            createIdempotencyKey: '',
            createRequestSignature: '',
            editIdempotencyKey: '',
            editRequestSignature: '',

            init() {
                this.rootElement = this.$el;
                this.writeEnabled = this.rootElement?.dataset.writeEnabled === '1';
                this.writeBlockMessage = String(this.rootElement?.dataset.writeBlockMessage || 'El modo demostración es de consulta.');
                formatTableTimes(document.getElementById('m04-template-table'));
                this.table = initDataTable();

                const modalElement = document.getElementById('m04-create-template-modal');
                if (modalElement && window.bootstrap?.Modal) {
                    this.modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
                    modalElement.addEventListener('hidden.bs.modal', () => this.resetForm());
                }
                const editModalElement = document.getElementById('m04-edit-template-modal');
                if (editModalElement && window.bootstrap?.Modal) {
                    this.editModal = window.bootstrap.Modal.getOrCreateInstance(editModalElement);
                    editModalElement.addEventListener('hidden.bs.modal', () => this.resetEditForm());
                }
                this.rootElement?.addEventListener('click', (event) => {
                    const editLink = event.target.closest('[data-m04-edit-template]');
                    if (editLink) {
                        event.preventDefault();
                        try {
                            this.openEdit(JSON.parse(editLink.dataset.m04EditTemplate || '{}'), editLink.closest('tr'));
                        } catch (_) {
                            this.error = 'No fue posible leer los datos actuales del turno. Actualiza el catálogo.';
                        }
                    }
                    const customizeLink = event.target.closest('[data-m04-customize-template]');
                    if (customizeLink) {
                        event.preventDefault();
                        try { this.openCustomize(JSON.parse(customizeLink.dataset.m04CustomizeTemplate || '{}')); }
                        catch (_) { this.error = 'No fue posible leer los datos del turno de origen. Actualiza el catálogo.'; }
                    }
                    const trigger = event.target.closest('.dropdown.dropstart > a[data-bs-toggle="dropdown"]');
                    if (trigger && !trigger.dataset.m04KeyboardBound) {
                        trigger.dataset.m04KeyboardBound = '1';
                    }
                });
                this.rootElement?.addEventListener('keydown', (event) => {
                    const trigger = event.target.closest('.dropdown.dropstart > a[data-bs-toggle="dropdown"]');
                    if (trigger && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        trigger.click();
                    }
                });
            },

            openCreate() {
                this.refreshWarning = '';
                this.resetForm();
                this.modal?.show();
                this.$nextTick(() => {
                    document.getElementById('m04-turn-name')?.focus();
                });
            },

            resetForm() {
                if (this.loading) return;
                this.createIdempotencyKey = '';
                this.createRequestSignature = '';
                this.customizationSource = null;
                this.name = '';
                this.startTime = '';
                this.endTime = '';
                this.breakStart = '';
                this.breakEnd = '';
                this.fieldErrors = { name: '', startTime: '', endTime: '', break: '' };
                this.error = '';
            },

            escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (character) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[character]));
            },

            idempotencyKeyFor(kind, payload) {
                const signature = JSON.stringify(payload);
                const keyName = kind === 'edit' ? 'editIdempotencyKey' : 'createIdempotencyKey';
                const signatureName = kind === 'edit' ? 'editRequestSignature' : 'createRequestSignature';
                if (!this[keyName] || this[signatureName] !== signature) {
                    const random = window.crypto?.randomUUID?.() || (Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2));
                    this[keyName] = 'm04-' + random.replace(/[^A-Za-z0-9]/g, '');
                    this[signatureName] = signature;
                }
                return this[keyName];
            },

            clearIdempotencyKey(kind) {
                if (kind === 'edit') {
                    this.editIdempotencyKey = '';
                    this.editRequestSignature = '';
                    return;
                }
                this.createIdempotencyKey = '';
                this.createRequestSignature = '';
            },

            cleanTemplateName(value) {
                const rawName = String(value ?? '').trim();
                let displayName = rawName.replace(/^Legacy\s+[^—–-]+[—–-]\s*/iu, '');
                displayName = displayName.replace(/\s*(?:[—–-]\s*)?\b\d{1,2}:\d{2}(?::\d{2})?\s*[—–-]\s*\d{1,2}:\d{2}(?::\d{2})?(?:\s*\(\+?\d+\s*d[ií]a\))?\s*$/u, '');
                displayName = displayName.replace(/[—–-]\s*$/u, '').trim();
                return displayName || rawName;
            },

            timeToMinutes(timeStr) {
                if (!timeStr || !/^\d{1,2}:\d{2}/.test(timeStr)) return null;
                const parts = timeStr.split(':');
                return (Number(parts[0]) || 0) * 60 + (Number(parts[1]) || 0);
            },

            formatTime(timeStr) {
                if (!timeStr) return '';
                const parts = timeStr.split(':');
                return `${String(parts[0]).padStart(2, '0')}:${String(parts[1]).padStart(2, '0')}`;
            },

            formatVisibleTime(timeStr, dayOffset = 0) {
                return formatVisibleTime(timeStr, dayOffset);
            },

            formatTableTimes(root) {
                formatTableTimes(root);
            },

            endpointMarkup(segment, timeKey, offsetKey) {
                return endpointMarkup(segment, timeKey, offsetKey, value => this.escapeHtml(value));
            },

            buildSegments() {
                return this.buildSegmentsFrom(this.startTime, this.endTime, this.breakStart, this.breakEnd, this.fieldErrors);
            },

            buildSegmentsFrom(startTime, endTime, breakStart, breakEnd, errors) {
                const startMin = this.timeToMinutes(startTime);
                const endMin = this.timeToMinutes(endTime);
                if (startMin === null || endMin === null) return null;

                const tIn = this.formatTime(startTime) + ':00';
                const tOut = this.formatTime(endTime) + ':00';

                const hasBreakStart = Boolean(breakStart);
                const hasBreakEnd = Boolean(breakEnd);

                if (hasBreakStart !== hasBreakEnd) {
                    errors.break = 'Debes indicar tanto el inicio como el fin del descanso, o dejar ambos vacíos.';
                    return null;
                }

                const isOvernight = endMin <= startMin;
                const shiftStartAbs = startMin;
                const shiftEndAbs = isOvernight ? 1440 + endMin : endMin;

                if (shiftEndAbs - shiftStartAbs > 1440) {
                    errors.endTime = 'La duración total del turno no puede exceder 24 horas.';
                    return null;
                }

                if (!hasBreakStart) {
                    return [
                        {
                            ordinal: 1,
                            tipo_segmento: 'WORK',
                            hora_inicio_local: tIn,
                            dia_offset_inicio: 0,
                            hora_fin_local: tOut,
                            dia_offset_fin: isOvernight ? 1 : 0,
                        },
                    ];
                }

                const bStartMin = this.timeToMinutes(breakStart);
                const bEndMin = this.timeToMinutes(breakEnd);
                const tBStart = this.formatTime(breakStart) + ':00';
                const tBEnd = this.formatTime(breakEnd) + ':00';

                let bStartAbs;
                let offBStart;
                if (!isOvernight) {
                    offBStart = 0;
                    bStartAbs = bStartMin;
                } else {
                    if (bStartMin >= startMin) {
                        offBStart = 0;
                        bStartAbs = bStartMin;
                    } else {
                        offBStart = 1;
                        bStartAbs = 1440 + bStartMin;
                    }
                }

                let bEndAbs;
                let offBEnd;
                if (!isOvernight) {
                    offBEnd = 0;
                    bEndAbs = bEndMin;
                } else {
                    if (offBStart === 0) {
                        if (bEndMin > bStartMin) {
                            offBEnd = 0;
                            bEndAbs = bEndMin;
                        } else {
                            offBEnd = 1;
                            bEndAbs = 1440 + bEndMin;
                        }
                    } else {
                        offBEnd = 1;
                        bEndAbs = 1440 + bEndMin;
                    }
                }

                if (bStartAbs <= shiftStartAbs || bEndAbs <= bStartAbs || bEndAbs >= shiftEndAbs) {
                    errors.break = 'El horario de descanso debe estar dentro del turno (después de la entrada y antes de la salida).';
                    return null;
                }

                return [
                    {
                        ordinal: 1,
                        tipo_segmento: 'WORK',
                        hora_inicio_local: tIn,
                        dia_offset_inicio: 0,
                        hora_fin_local: tBStart,
                        dia_offset_fin: offBStart,
                    },
                    {
                        ordinal: 2,
                        tipo_segmento: 'BREAK',
                        hora_inicio_local: tBStart,
                        dia_offset_inicio: offBStart,
                        hora_fin_local: tBEnd,
                        dia_offset_fin: offBEnd,
                    },
                    {
                        ordinal: 3,
                        tipo_segmento: 'WORK',
                        hora_inicio_local: tBEnd,
                        dia_offset_inicio: offBEnd,
                        hora_fin_local: tOut,
                        dia_offset_fin: isOvernight ? 1 : 0,
                    },
                ];
            },

            resetEditForm() {
                if (this.editLoading) return;
                this.clearIdempotencyKey('edit');
                this.editTemplate = null;
                this.editRowNode = null;
                this.editName = '';
                this.editStartTime = '';
                this.editEndTime = '';
                this.editBreakStart = '';
                this.editBreakEnd = '';
                this.editVersion = 0;
                this.editFieldErrors = { name: '', startTime: '', endTime: '', break: '' };
                this.editError = '';
            },

            openEdit(template, rowNode = null) {
                if (this.rootElement?.dataset.editEnabled !== '1' || !template?.key || !template?.version || !template?.fields) {
                    this.error = 'No tienes permiso para editar este turno o sus datos no están disponibles.';
                    return;
                }
                this.resetEditForm();
                this.editTemplate = template;
                this.editRowNode = rowNode?.dataset?.templateKey === String(template.key) ? rowNode : null;
                this.editVersion = Number(template.version);
                this.editName = this.cleanTemplateName(template.name);
                this.editStartTime = String(template.fields.start_time || '');
                this.editEndTime = String(template.fields.end_time || '');
                this.editBreakStart = String(template.fields.break_start || '');
                this.editBreakEnd = String(template.fields.break_end || '');
                this.editModal?.show();
                this.$nextTick(() => document.getElementById('m04-edit-turn-name')?.focus());
            },

            openCustomize(source) {
                if (this.rootElement?.dataset.editEnabled !== '1' || !source?.key || !source?.version || !source?.fields || !Array.isArray(source?.segments)) {
                    this.error = 'No tienes permiso para personalizar este turno o los datos de origen no están disponibles.';
                    return;
                }
                this.resetForm();
                this.customizationSource = { key: String(source.key), version: Number(source.version) };
                this.name = this.cleanTemplateName(source.name);
                this.startTime = String(source.fields.start_time || '');
                this.endTime = String(source.fields.end_time || '');
                this.breakStart = String(source.fields.break_start || '');
                this.breakEnd = String(source.fields.break_end || '');
                this.modal?.show();
                this.$nextTick(() => document.getElementById('m04-turn-name')?.focus());
            },

            actionMarkup(template) {
                if (this.rootElement?.dataset.editEnabled !== '1' || !template?.clave || !template?.version) return '';
                if (String(template.scope || '').toUpperCase() !== 'STATION'
                    || Number(template.id_estacion || 0) !== Number(this.rootElement?.dataset.workplaceId || 0)) return '';
                const fields = this.projectEditableFields(template.segmentos || []);
                if (!fields) return '';
                const payload = this.escapeHtml(JSON.stringify({
                    key: template.clave, version: Number(template.version), name: this.cleanTemplateName(template.nombre),
                    type: template.tipo_turno, fields,
                }));
                return '<div class="dropdown dropstart"><a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones del turno" aria-expanded="false" role="button" tabindex="0"><i class="ti ti-dots-vertical fs-6"></i></a><ul class="dropdown-menu"><li><a class="dropdown-item pointer" href="#" data-m04-edit-template="' + payload + '"><i class="ti ti-edit"></i> Editar</a></li></ul></div>';
            },

            projectEditableFields(segments) {
                const ordered = Array.isArray(segments) ? [...segments].sort((a, b) => Number(a.ordinal || 0) - Number(b.ordinal || 0)) : [];
                const types = ordered.map(segment => String(segment.tipo_segmento || '').toUpperCase());
                if (!(types.length === 1 && types[0] === 'WORK') && !(types.join(',') === 'WORK,BREAK,WORK')) return null;
                const abs = (time, offset) => Number(offset || 0) * 1440 + this.timeToMinutes(String(time || '').substring(0, 5));
                for (let i = 0; i < ordered.length; i += 1) {
                    const segment = ordered[i];
                    if (!/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:00$/.test(String(segment.hora_inicio_local || ''))
                        || !/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:00$/.test(String(segment.hora_fin_local || ''))
                        || abs(segment.hora_fin_local, segment.dia_offset_fin) <= abs(segment.hora_inicio_local, segment.dia_offset_inicio)
                        || (i > 0 && abs(segment.hora_inicio_local, segment.dia_offset_inicio) !== abs(ordered[i - 1].hora_fin_local, ordered[i - 1].dia_offset_fin))) return null;
                }
                const work = ordered.filter(segment => String(segment.tipo_segmento).toUpperCase() === 'WORK');
                const rest = ordered.filter(segment => String(segment.tipo_segmento).toUpperCase() === 'BREAK');
                if (Number(ordered[0].dia_offset_inicio) !== 0) return null;
                return {
                    start_time: String(work[0].hora_inicio_local).substring(0, 5),
                    end_time: String(work[work.length - 1].hora_fin_local).substring(0, 5),
                    break_start: rest.length ? String(rest[0].hora_inicio_local).substring(0, 5) : '',
                    break_end: rest.length ? String(rest[0].hora_fin_local).substring(0, 5) : '',
                };
            },

            updateTemplateRow(template, preferredRowNode = null) {
                if (!this.table || !template) return false;
                const rowNode = preferredRowNode?.dataset?.templateKey === String(template.clave)
                    ? preferredRowNode
                    : this.table.rows().nodes().toArray().find(node => node.dataset.templateKey === String(template.clave));
                if (!rowNode) return false;
                const segments = Array.isArray(template.segmentos) ? [...template.segmentos].sort((a, b) => Number(a.ordinal || 0) - Number(b.ordinal || 0)) : [];
                const work = segments.filter(segment => String(segment.tipo_segmento || '').toUpperCase() === 'WORK');
                const breaks = segments.filter(segment => String(segment.tipo_segmento || '').toUpperCase() === 'BREAK');
                const cells = rowNode.children;
                if (!cells || cells.length < 7) return false;
                cells[0].textContent = this.cleanTemplateName(template.nombre);
                cells[1].innerHTML = work.length ? this.endpointMarkup(work[0], 'hora_inicio_local', 'dia_offset_inicio') : '—';
                cells[2].innerHTML = breaks.length ? breaks.map(segment => this.endpointMarkup(segment, 'hora_inicio_local', 'dia_offset_inicio')).join('<br>') : '—';
                cells[3].innerHTML = breaks.length ? breaks.map(segment => this.endpointMarkup(segment, 'hora_fin_local', 'dia_offset_fin')).join('<br>') : '—';
                cells[4].innerHTML = work.length ? this.endpointMarkup(work[work.length - 1], 'hora_fin_local', 'dia_offset_fin') : '—';
                cells[5].innerHTML = Number(template.es_activo) === 1 ? '<span class="badge checador-status-badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-success-subtle border border-success-subtle text-body-emphasis"><i class="ti ti-circle-check" aria-hidden="true"></i>Activa</span>' : '<span class="badge checador-status-badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-body-secondary border text-body-emphasis"><i class="ti ti-minus" aria-hidden="true"></i>Inactiva</span>';
                cells[6].innerHTML = this.actionMarkup(template);
                [cells[1], cells[2], cells[3], cells[4]].forEach(cell => cell.removeAttribute('data-order'));
                formatTableTimes(rowNode);
                rowNode.dataset.templateVersion = String(template.version);
                this.table.row(rowNode).invalidate('dom').draw(false);
                return true;
            },

            addTemplateRow(template) {
                if (!this.table || !template) return false;
                const segments = Array.isArray(template.segmentos) ? [...template.segmentos].sort((a, b) => Number(a.ordinal || 0) - Number(b.ordinal || 0)) : [];
                const work = segments.filter(segment => String(segment.tipo_segmento || '').toUpperCase() === 'WORK');
                const breaks = segments.filter(segment => String(segment.tipo_segmento || '').toUpperCase() === 'BREAK');
                const node = document.createElement('tr');
                node.dataset.templateKey = String(template.clave);
                node.dataset.templateVersion = String(template.version);
                const cells = [
                    ['th', this.escapeHtml(this.cleanTemplateName(template.nombre))],
                    ['td', work.length ? this.endpointMarkup(work[0], 'hora_inicio_local', 'dia_offset_inicio') : '—'],
                    ['td', breaks.length ? breaks.map(s => this.endpointMarkup(s, 'hora_inicio_local', 'dia_offset_inicio')).join('<br>') : '—'],
                    ['td', breaks.length ? breaks.map(s => this.endpointMarkup(s, 'hora_fin_local', 'dia_offset_fin')).join('<br>') : '—'],
                    ['td', work.length ? this.endpointMarkup(work[work.length - 1], 'hora_fin_local', 'dia_offset_fin') : '—'],
                    ['td', Number(template.es_activo) === 1 ? '<span class="badge checador-status-badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-success-subtle border border-success-subtle text-body-emphasis"><i class="ti ti-circle-check" aria-hidden="true"></i>Activa</span>' : '<span class="badge checador-status-badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-body-secondary border text-body-emphasis"><i class="ti ti-minus" aria-hidden="true"></i>Inactiva</span>'],
                    ['td', this.actionMarkup(template)],
                ];
                cells.forEach(([tagName, markup], index) => {
                    const cell = document.createElement(tagName);
                    if (index === 0) cell.scope = 'row';
                    cell.innerHTML = markup;
                    node.appendChild(cell);
                });
                formatTableTimes(node);
                const addedRow = this.table.row.add(node);
                if (!addedRow.node()) return false;
                addedRow.draw(false);
                return true;
            },

            async saveEdit() {
                if (this.editLoading) return;
                if (!this.writeEnabled) {
                    this.editError = this.writeBlockMessage;
                    return;
                }
                this.editFieldErrors = { name: '', startTime: '', endTime: '', break: '' };
                this.editError = '';
                const name = String(this.editName || '').trim();
                if (!name) { this.editFieldErrors.name = 'El nombre del turno es obligatorio.'; return; }
                if (name.length > 128) { this.editFieldErrors.name = 'El nombre no puede exceder 128 caracteres.'; return; }
                if (!this.editStartTime) { this.editFieldErrors.startTime = 'La hora de entrada es obligatoria.'; return; }
                if (!this.editEndTime) { this.editFieldErrors.endTime = 'La hora de salida final es obligatoria.'; return; }
                const segments = this.buildSegmentsFrom(this.editStartTime, this.editEndTime, this.editBreakStart, this.editBreakEnd, this.editFieldErrors);
                if (!segments) return;
                const workplaceId = Number(this.rootElement?.dataset.workplaceId || 0);
                if (!Number.isSafeInteger(workplaceId) || workplaceId < 1 || !this.editTemplate) {
                    this.editError = 'No se pudo confirmar la estación o el turno seleccionado.';
                    return;
                }
                this.editLoading = true;
                try {
                    const csrfToken = this.rootElement.dataset.csrfToken
                        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const payload = {
                        operation: 'version',
                        template_key: this.editTemplate.key,
                        expected_version: Number(this.editTemplate.version),
                        template_name: name,
                        workplace_id: workplaceId,
                        segments,
                    };
                    payload.idempotency_key = this.idempotencyKeyFor('edit', payload);
                    const response = await window.axios.post(this.rootElement.dataset.editUrl, payload, {
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    });
                    const body = response.data || {};
                    if (body.ok !== true || !body.template) throw { response: { status: response.status, data: body } };
                    this.clearIdempotencyKey('edit');
                    if (!this.updateTemplateRow(body.template, this.editRowNode)) {
                        this.refreshWarning = 'La versión quedó guardada y confirmada, pero no se pudo refrescar la fila. Se actualizará el catálogo seleccionado; no vuelvas a guardar.';
                        this.editModal?.hide();
                        window.location.reload();
                        return;
                    }
                    if (document.getElementById('m04-global-template-table')) {
                        this.editModal?.hide();
                        window.location.reload();
                        return;
                    }
                    this.success = 'La versión ' + body.template.version + ' del turno «' + body.template.nombre + '» fue guardada y confirmada.';
                    this.editModal?.hide();
                } catch (failure) {
                    const status = Number(failure?.response?.status || 0);
                    const body = failure?.response?.data || {};
                    this.editError = body.message || (status === 401
                        ? 'Tu sesión expiró. Inicia sesión nuevamente.'
                        : status === 403 ? 'No tienes permiso para editar turnos en esta estación.'
                            : status === 409 ? 'El turno cambió desde que abriste el formulario. Actualiza el catálogo antes de reintentar.'
                                : status === 419 ? 'La protección de sesión venció. Actualiza la página antes de guardar.'
                                    : status >= 500 ? 'No se pudo confirmar la nueva versión. Consulta el catálogo antes de reintentar.'
                                        : failure?.message || 'No fue posible guardar los cambios.');
                } finally {
                    this.editLoading = false;
                }
            },

            async save() {
                if (this.loading) return;
                if (!this.writeEnabled) {
                    this.error = this.writeBlockMessage;
                    return;
                }
                this.fieldErrors = { name: '', startTime: '', endTime: '', break: '' };
                this.error = '';
                this.refreshWarning = '';
                this.success = '';

                const name = String(this.name ?? '').trim();
                if (!name) {
                    this.fieldErrors.name = 'El nombre del turno es obligatorio.';
                    document.getElementById('m04-turn-name')?.focus();
                    return;
                }
                if (name.length > 128) {
                    this.fieldErrors.name = 'El nombre no puede exceder 128 caracteres.';
                    return;
                }

                if (!this.startTime) {
                    this.fieldErrors.startTime = 'La hora de entrada es obligatoria.';
                    return;
                }
                if (!this.endTime) {
                    this.fieldErrors.endTime = 'La hora de salida final es obligatoria.';
                    return;
                }

                const segments = this.buildSegments();
                if (!segments) return;

                const workplaceId = Number(this.rootElement.dataset.workplaceId || 0);
                if (!Number.isSafeInteger(workplaceId) || workplaceId < 1) {
                    this.error = 'No se ha identificado una estación autorizada válida.';
                    return;
                }

                this.loading = true;

                const payload = this.customizationSource
                    ? { operation: 'customize', template_name: name, workplace_id: workplaceId, source_template_key: this.customizationSource.key,
                        source_template_version: this.customizationSource.version, segments }
                    : { operation: 'create', template_name: name, workplace_id: workplaceId, confirm_action: '1', segments };
                payload.idempotency_key = this.idempotencyKeyFor('create', payload);

                try {
                    const csrfToken = this.rootElement.dataset.csrfToken
                        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || '';

                    const response = await window.axios.post(this.customizationSource ? this.rootElement.dataset.customizeUrl : this.rootElement.dataset.saveUrl, payload, {
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });

                    const body = response.data || {};
                    if (body.ok !== true) {
                        throw { response: { status: response.status, data: body } };
                    }
                    this.clearIdempotencyKey('create');

                    const confirmed = body.template || {};
                    if (!confirmed.clave || Number(confirmed.id) < 1 || Number(confirmed.version) < 1
                        || String(confirmed.scope || '').toUpperCase() !== 'STATION'
                        || Number(confirmed.id_estacion || 0) !== workplaceId) {
                        this.error = 'El servidor no confirmó que el turno pertenece al catálogo de esta localidad. Consulta M04 antes de volver a guardar.';
                        return;
                    }
                    const confirmedSegments = Array.isArray(confirmed.segmentos) ? [...confirmed.segmentos] : null;
                    if (!confirmedSegments || confirmedSegments.length === 0) {
                        this.error = 'El servidor no confirmó horas persistidas para el turno. No vuelvas a guardar; actualiza el catálogo para verificarlo.';
                        return;
                    }

                    confirmedSegments.sort((a, b) => Number(a.ordinal || 0) - Number(b.ordinal || 0));
                    const workSegs = confirmedSegments.filter(s => String(s.tipo_segmento || '').toUpperCase() === 'WORK');
                    const breakSegs = confirmedSegments.filter(s => String(s.tipo_segmento || '').toUpperCase() === 'BREAK');
                    const validTime = value => /^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(String(value || ''));
                    if (workSegs.length === 0 || confirmedSegments.some(segment =>
                        !validTime(segment.hora_inicio_local) || !validTime(segment.hora_fin_local)
                        || ![0, 1].includes(Number(segment.dia_offset_inicio))
                        || ![0, 1].includes(Number(segment.dia_offset_fin)))) {
                        this.error = 'El servidor no confirmó una entrada y salida persistidas válidas. No vuelvas a guardar; actualiza el catálogo para verificarlo.';
                        return;
                    }

                    if (this.customizationSource) {
                        this.refreshWarning = 'La personalización se guardó y confirmó. Se actualizará el catálogo; no la guardes nuevamente.';
                        this.modal?.hide();
                        window.location.reload();
                        return;
                    }
                    const colTurno = this.escapeHtml(confirmed.nombre || name);
                    if (!this.table) {
                        this.table = initDataTable();
                    }
                    const confirmedTemplate = { ...confirmed, segmentos: confirmedSegments };
                    if (!this.updateTemplateRow(confirmedTemplate) && !this.addTemplateRow(confirmedTemplate)) {
                        const workplaceName = String(this.rootElement?.dataset.workplaceName || 'la localidad seleccionada');
                        this.refreshWarning = `El turno quedó guardado y confirmado en ${workplaceName}. Se recargará el catálogo; no lo guardes de nuevo.`;
                        this.modal?.hide();
                        window.location.reload();
                        return;
                    }
                    this.success = this.customizationSource
                        ? `La personalización local de «${confirmed.nombre || name}» fue confirmada en ${String(this.rootElement?.dataset.workplaceName || 'la localidad seleccionada')}.`
                        : `El turno «${confirmed.nombre || name}» fue registrado y confirmado en ${String(this.rootElement?.dataset.workplaceName || 'la localidad seleccionada')}.`;
                    this.modal?.hide();
                } catch (failure) {
                    const status = Number(failure?.response?.status || 0);
                    const body = failure?.response?.data || {};
                    this.error = body.message || (status === 401
                        ? 'Tu sesión expiró. Inicia sesión nuevamente.'
                        : status === 403
                            ? 'No tienes permiso para administrar turnos en esta estación.'
                            : status === 409
                                ? 'Ya existe una personalización incompatible o un turno con ese nombre en la estación.'
                                : status === 419
                                    ? 'La protección de sesión venció. Actualiza la página antes de guardar.'
                                    : status >= 500
                                        ? 'El servidor o la API no pudieron confirmar el registro del turno. Consulta el catálogo antes de reintentar.'
                                        : (failure?.message || 'No fue posible guardar el turno. Revisa los datos e inténtalo de nuevo.'));
                } finally {
                    this.loading = false;
                }
            },
        }));
    });
})();
