document.addEventListener('alpine:init', () => {
    Alpine.data('attendancePolicyForm', () => ({
        current: null,
        next: null,
        history: [],
        historyTable: null,
        historyComplete: true,
        actorNames: {},
        editModal: null,
        historyModal: null,
        editorDefaults: null,
        success: '',
        error: '',

        initialize(element) {
            const parse = (value, fallback) => {
                try { return JSON.parse(value || ''); } catch (_) { return fallback; }
            };
            const current = parse(element.dataset.currentPolicy, null);
            const next = parse(element.dataset.nextPolicy, null);
            const currentDate = element.dataset.currentDate || '';
            this.current = current && current.id ? current : null;
            this.next = next && next.id && next.effective_from_local_date > currentDate ? next : null;
            this.historyComplete = element.dataset.historyComplete === 'true';
            this.actorNames = parse(element.dataset.actorNames, {});
            this.history = parse(element.dataset.history, []).filter((item) => item && item.id);
            this.history = this.withActorNames(this.history);
            this.history.sort((a, b) => String(b.effective_from_local_date).localeCompare(String(a.effective_from_local_date)));

            const editModalElement = element.querySelector('#attendance-policy-edit-modal');
            const historyModalElement = element.querySelector('#attendance-policy-history-modal');
            const editButton = element.querySelector('#attendance-policy-edit-button');
            if (editModalElement && window.bootstrap?.Modal) {
                this.editModal = bootstrap.Modal.getOrCreateInstance(editModalElement);
                editModalElement.addEventListener('hidden.bs.modal', () => this.resetEditor());
                editButton?.addEventListener('click', () => this.openEditModal());
            }
            if (historyModalElement && window.bootstrap?.Modal) {
                this.historyModal = bootstrap.Modal.getOrCreateInstance(historyModalElement);
                historyModalElement.addEventListener('shown.bs.modal', () => this.initializeHistoryTable());
            }
        },

        withActorNames(rows) {
            return rows.map((row) => ({
                ...row,
                actor_name: row.actor_name || this.actorNames[String(row.actor_portal_user_id)] || 'No disponible',
            }));
        },

        openEditModal() {
            const modalElement = document.getElementById('attendance-policy-edit-modal');
            const form = modalElement?.querySelector('form');
            if (!form || !modalElement) {
                this.error = 'No se pudo abrir el formulario de configuración. Actualiza la página e inténtalo de nuevo.';
                return;
            }
            this.editModal = bootstrap.Modal.getOrCreateInstance(modalElement);
            this.error = '';
            this.success = '';
            this.editorDefaults = {
                effective_from_local_date: form.elements.effective_from_local_date.value,
                retardo_tolerance_minutes: form.elements.retardo_tolerance_minutes.value,
                incidence_deadline_days: form.elements.incidence_deadline_days.value,
            };
            this.editModal.show();
        },

        openHistoryModal() {
            this.historyModal?.show();
        },

        initializeHistoryTable() {
            const tableElement = this.$el.querySelector('#attendance-policy-history-table');
            if (!tableElement || !window.jQuery?.fn?.DataTable) return;
            if (this.historyTable) {
                this.historyTable.columns.adjust();
                return;
            }

            this.historyTable = window.jQuery(tableElement).DataTable({
                data: this.history,
                processing: false,
                serverSide: false,
                autoWidth: false,
                stateSave: false,
                pageLength: 10,
                order: [[1, 'desc']],
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                columns: [
                    {
                        data: 'policy_version',
                        render: (value, type) => type === 'display' || type === 'filter'
                            ? (value ? `#${this.escapeHtml(value)}` : '—')
                            : Number(value || 0),
                    },
                    { data: 'effective_from_local_date', render: (value, type) => this.renderText(value, type) },
                    {
                        data: 'retardo_tolerance_minutes',
                        render: (value, type) => type === 'display' || type === 'filter' ? `${this.escapeHtml(value ?? '—')} min` : Number(value ?? -1),
                    },
                    {
                        data: 'incidence_deadline_days',
                        render: (value, type) => type === 'display' || type === 'filter' ? `${this.escapeHtml(value ?? '—')} días` : Number(value ?? -1),
                    },
                    { data: 'actor_name', render: (value, type) => this.renderText(value, type) },
                ],
            });
        },

        renderText(value, type) {
            const text = String(value ?? '—');
            return type === 'display' || type === 'filter' ? this.escapeHtml(text) : text;
        },

        escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[character]));
        },

        syncHistoryTable() {
            if (!this.historyTable) return;
            this.historyTable.clear().rows.add(this.history).draw(false);
        },

        applyReadback(detail) {
            if (!detail || !detail.policy) return;
            const policy = detail.policy;
            const currentDate = detail.current_local_date || '';
            const latest = detail.latest_scheduled_policy && detail.latest_scheduled_policy.id
                ? detail.latest_scheduled_policy : policy;

            if (policy.effective_from_local_date <= currentDate) {
                this.current = policy;
            }
            this.next = latest.effective_from_local_date > currentDate ? latest : null;

            const byId = new Map(this.history.map((item) => [Number(item.id), item]));
            [policy, latest].forEach((item) => {
                if (!item || !item.id) return;
                const actorName = this.actorNames[String(item.actor_portal_user_id)] || 'Tú';
                byId.set(Number(item.id), { ...item, actor_name: actorName });
            });
            this.history = Array.from(byId.values()).sort((a, b) =>
                String(b.effective_from_local_date).localeCompare(String(a.effective_from_local_date))
            );
            this.syncHistoryTable();
            this.updateEditorDefaults(latest);
            this.error = '';
            this.success = detail.message || 'La configuración se guardó y quedó confirmada.';
        },

        updateEditorDefaults(policy) {
            const form = this.$el.querySelector('#attendance-policy-edit-modal form');
            if (!form || !policy) return;
            const effectiveFrom = this.nextDate(policy.effective_from_local_date);
            this.editorDefaults = {
                effective_from_local_date: effectiveFrom,
                retardo_tolerance_minutes: String(policy.retardo_tolerance_minutes ?? ''),
                incidence_deadline_days: String(policy.incidence_deadline_days ?? ''),
            };
            const date = form.elements.effective_from_local_date;
            date.min = effectiveFrom;
            date.value = effectiveFrom;
            form.elements.retardo_tolerance_minutes.value = this.editorDefaults.retardo_tolerance_minutes;
            form.elements.incidence_deadline_days.value = this.editorDefaults.incidence_deadline_days;
            form.elements.confirm_policy.checked = false;
        },

        nextDate(dateValue) {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dateValue || ''));
            if (!match) return '';
            const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]) + 1));
            return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
        },

        resetEditor() {
            const form = this.$el.querySelector('#attendance-policy-edit-modal form');
            if (!form) return;
            if (this.editorDefaults) {
                form.elements.effective_from_local_date.value = this.editorDefaults.effective_from_local_date;
                form.elements.retardo_tolerance_minutes.value = this.editorDefaults.retardo_tolerance_minutes;
                form.elements.incidence_deadline_days.value = this.editorDefaults.incidence_deadline_days;
            }
            form.elements.confirm_policy.checked = false;
            window.dispatchEvent(new Event('attendance-policy-modal-reset'));
        },
    }));

    Alpine.data('attendancePolicyEditor', () => ({
        loading: false,
        fieldErrors: {},
        error: '',

        clearFieldError(field) {
            this.fieldErrors[field] = '';
            this.error = '';
        },

        resetForm() {
            this.fieldErrors = {};
            this.error = '';
            this.loading = false;
        },

        async submit(event) {
            const form = event.currentTarget;
            if (this.loading) return;
            this.fieldErrors = {};
            this.error = '';
            if (!form.checkValidity()) {
                const date = form.elements.effective_from_local_date;
                const toleranceInput = form.elements.retardo_tolerance_minutes;
                const deadlineInput = form.elements.incidence_deadline_days;
                const tolerance = Number(toleranceInput.value);
                const deadline = Number(deadlineInput.value);
                if (!date.value || date.validity.rangeUnderflow) {
                    this.fieldErrors.effective_from_local_date = 'Selecciona una fecha de vigencia permitida.';
                }
                if (!Number.isInteger(tolerance) || tolerance < 0 || tolerance > 120) {
                    this.fieldErrors.retardo_tolerance_minutes = 'Usa un número entero de 0 a 120 minutos.';
                }
                if (!Number.isInteger(deadline) || deadline < 0 || deadline > 30) {
                    this.fieldErrors.incidence_deadline_days = 'Usa un número entero de 0 a 30 días.';
                }
                if (!form.elements.confirm_policy.checked) {
                    this.fieldErrors.confirm_policy = 'Confirma los valores para continuar.';
                }
                form.reportValidity();
                return;
            }

            this.loading = true;
            const submitButton = form.querySelector('[type="submit"]');
            const cancelButton = form.querySelector('[data-bs-dismiss="modal"]');
            if (submitButton) submitButton.disabled = true;
            if (cancelButton) cancelButton.disabled = true;
            try {
                const response = await axios.post(form.action, new FormData(form), {
                    headers: { Accept: 'application/json' },
                });
                const body = response.data || {};
                if (response.status !== 200 || body.ok !== true || !body.policy || !body.policy.id) {
                    throw { response: { status: response.status, data: body } };
                }
                const key = form.elements.idempotency_key;
                if (key && body.next_idempotency_key) key.value = body.next_idempotency_key;
                form.elements.confirm_policy.checked = false;
                form.dispatchEvent(new CustomEvent('attendance-policy-confirmed', {
                    bubbles: true,
                    detail: body,
                }));
                bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
            } catch (failure) {
                const response = failure && failure.response;
                let status = Number(response && response.status);
                const body = response && response.data ? response.data : {};
                if (status === 200 && response && response.request
                    && /\/login(?:\?|$)/i.test(String(response.request.responseURL || ''))) status = 401;
                const messages = {
                    401: 'Tu sesión expiró. Inicia sesión nuevamente y vuelve a consultar la localidad.',
                    403: 'No tienes permiso para modificar esta configuración.',
                    409: 'La configuración entra en conflicto con otra versión. Actualiza la pantalla y revisa los cambios.',
                    422: body.message || 'Revisa la fecha y los valores permitidos.',
                    419: 'La protección de sesión venció. Actualiza la página antes de volver a guardar.',
                };
                const message = messages[status] || (status >= 500
                    ? 'El servidor no pudo confirmar el guardado. Reintenta con la misma solicitud.'
                    : 'No se pudo completar la solicitud. Comprueba tu conexión e inténtalo de nuevo.');
                this.error = message;
                form.dispatchEvent(new CustomEvent('attendance-policy-error', { bubbles: true, detail: message }));
            } finally {
                this.loading = false;
                if (submitButton) submitButton.disabled = false;
                if (cancelButton) cancelButton.disabled = false;
            }
        },
    }));
});
