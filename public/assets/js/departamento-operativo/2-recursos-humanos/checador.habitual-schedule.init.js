document.addEventListener('alpine:init', () => {
    const assignmentErrorMessages = {
        assignment_conflict: 'Ya existe una asignación incompatible para ese periodo.',
        journey_already_materialized: 'La jornada ya está materializada. No se modificó el horario; solicita el procedimiento autorizado de corrección o revisión de Jornada.',
        membership_unverified: 'No se pudo verificar la adscripción del trabajador para la fecha seleccionada.',
        membership_conflict: 'La adscripción del trabajador es ambigua para la fecha seleccionada.',
        template_not_allowed: 'El turno no pertenece al catálogo autorizado de esta localidad.',
        invalid_template_segments: 'El turno no tiene segmentos laborales válidos persistidos.',
        invalid_effective_date: 'La fecha no puede ser anterior a hoy y debe quedar dentro del periodo consultado.',
        timezone_unresolved: 'No se pudo confirmar la zona horaria de la localidad para esa fecha.',
        database_write_failed: 'MariaDB no confirmó el guardado; la asignación no se declara guardada.',
        readback_failed: 'El guardado ocurrió, pero no pudo confirmarse mediante lectura posterior. Consulta antes de reintentar.',
        readback_mismatch: 'La lectura posterior no coincide con el cambio; no vuelvas a enviarlo hasta revisar la programación.',
        audit_readback_failed: 'No se pudo confirmar la auditoría del cambio; no se declara guardado.',
    };
    const assignmentErrorMessage = (code) => assignmentErrorMessages[String(code || '')] || '';
    const scheduleModalSelectors = [
        '#habitual-schedule-assignment-modal',
        '#habitual-schedule-replacement-modal',
        '#temporary-schedule-assignment-modal',
    ];
    let activeScheduleModal = null;
    let queuedScheduleModal = null;
    document.addEventListener('show.bs.modal', (event) => {
        const target = event.target;
        if (!target?.matches?.(scheduleModalSelectors.join(',')) || event.defaultPrevented) return;

        if (!activeScheduleModal) {
            activeScheduleModal = scheduleModalSelectors
                .map((selector) => document.querySelector(selector))
                .find((modal) => modal && modal !== target && modal.classList.contains('show')) || null;
        }
        if (activeScheduleModal && activeScheduleModal !== target) {
            event.preventDefault();
            queuedScheduleModal = { element: target, relatedTarget: event.relatedTarget || null };
            const activeInstance = window.bootstrap?.Modal?.getInstance(activeScheduleModal)
                || window.bootstrap?.Modal?.getOrCreateInstance(activeScheduleModal);
            activeInstance?.hide();
            return;
        }
        activeScheduleModal = target;
    });
    document.addEventListener('shown.bs.modal', (event) => {
        if (activeScheduleModal !== event.target || !queuedScheduleModal) return;
        // Bootstrap ignores hide() while the opening transition is still running.
        const activeInstance = window.bootstrap?.Modal?.getInstance(event.target);
        activeInstance?.hide();
    });
    document.addEventListener('hidden.bs.modal', (event) => {
        if (!event.target?.matches?.(scheduleModalSelectors.join(','))) return;
        if (activeScheduleModal === event.target) activeScheduleModal = null;
        const queued = queuedScheduleModal;
        if (!queued || !queued.element?.isConnected) return;
        queuedScheduleModal = null;
        window.bootstrap?.Modal?.getOrCreateInstance(queued.element)?.show(queued.relatedTarget || undefined);
    });
    const scheduleSelect2Targets = [
        ['#habitual-schedule-assignment-modal', 'select[name^="schedule["]'],
        ['#habitual-schedule-replacement-modal', '#habitual-replacement-source, #habitual-replacement-template'],
        ['#temporary-schedule-assignment-modal', '#temporary-assignment-template'],
    ];
    const normalizeScheduleSearch = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLocaleLowerCase('es-MX')
        .replace(/\s+/g, '');
    const scheduleSelectMatcher = (params, data) => {
        if (!data || !String(params?.term || '').trim()) return data;
        if (Array.isArray(data.children) && data.children.length) {
            const children = data.children.map((child) => scheduleSelectMatcher(params, child)).filter(Boolean);
            return children.length ? { ...data, children } : null;
        }
        return normalizeScheduleSearch(data.text).includes(normalizeScheduleSearch(params.term)) ? data : null;
    };
    let scheduleSelect2Dependencies = null;
    const loadScheduleAsset = (src) => new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', () => reject(new Error('No se pudo cargar un recurso local de búsqueda de horarios.')), { once: true });
        document.body.append(script);
    });
    const ensureScheduleStylesheet = (href) => {
        const path = new URL(href, location.href).pathname;
        const existing = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
            .find((link) => new URL(link.href, location.href).pathname === path);
        if (existing?.sheet) return Promise.resolve();
        const link = existing || document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        return new Promise((resolve, reject) => {
            link.addEventListener('load', resolve, { once: true });
            link.addEventListener('error', () => reject(new Error('No se pudo cargar el estilo local de los selectores de horarios.')), { once: true });
            if (!existing) document.head.append(link);
        });
    };
    const ensureScheduleSelect2 = () => {
        if (scheduleSelect2Dependencies) return scheduleSelect2Dependencies;
        scheduleSelect2Dependencies = (async () => {
            await ensureScheduleStylesheet('/assets/libs/select2/dist/css/select2.min.css');
            await ensureScheduleStylesheet('/assets/css/departamento-operativo/2-recursos-humanos/checador-schedule-select2.css');
            if (!window.jQuery) await loadScheduleAsset('/assets/js/vendor.min.js');
            if (!window.jQuery?.fn?.select2) await loadScheduleAsset('/assets/libs/select2/dist/js/select2.full.min.js');
            return Boolean(window.jQuery?.fn?.select2);
        })().catch((error) => {
            scheduleSelect2Dependencies = null;
            throw error;
        });
        return scheduleSelect2Dependencies;
    };
    const bindScheduleSelect2 = () => {
        scheduleSelect2Targets.forEach(([modalSelector, selectSelector]) => {
            const modal = document.querySelector(modalSelector);
            if (!modal || modal.dataset.scheduleSelect2Bound === '1') return;
            modal.addEventListener('shown.bs.modal', async () => {
                try {
                    if (!(await ensureScheduleSelect2()) || !modal.classList.contains('show')) return;
                } catch (error) {
                    console.warn('[Checador M03] La búsqueda de turnos no está disponible; se conserva el selector nativo.', error);
                    return;
                }
                jQuery(modal).find(selectSelector).each((_index, select) => {
                    const $select = jQuery(select);
                    if ($select.hasClass('select2-hidden-accessible')) return;
                    $select.select2({
                        dropdownParent: jQuery(modal),
                        width: '100%',
                        minimumResultsForSearch: 0,
                        matcher: scheduleSelectMatcher,
                        language: {
                            noResults: () => 'No hay horarios que coincidan.',
                            searching: () => 'Buscando…',
                        },
                    });
                    if (select.id === 'habitual-replacement-source') {
                        $select.on('select2:select.m03Schedule select2:clear.m03Schedule', () => {
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    }
                });
            });
            modal.addEventListener('hidden.bs.modal', () => {
                if (!window.jQuery?.fn?.select2) return;
                jQuery(modal).find('select.select2-hidden-accessible').each((_index, select) => {
                    const $select = jQuery(select);
                    $select.off('.m03Schedule');
                    $select.select2('destroy');
                });
            });
            modal.dataset.scheduleSelect2Bound = '1';
        });
    };
    bindScheduleSelect2();

    Alpine.data('temporaryScheduleAssignment', () => ({
        loading: false,
        completed: false,
        error: '',
        status: '',
        habitualReference: 'Selecciona un trabajador y una fecha para consultar el horario habitual.',
        workerEligibilityStatus: 'Selecciona una fecha para consultar el personal con adscripción verificada.',
        modal: null,
        habitualReferenceByWorker: {},

        initialize(element) {
            const modalElement = element.id === 'temporary-schedule-assignment-modal'
                ? element
                : element.querySelector('#temporary-schedule-assignment-modal');
            const referenceElement = document.getElementById('temporary-schedule-habitual-reference-data');
            try {
                this.habitualReferenceByWorker = JSON.parse(referenceElement?.textContent || '{}');
            } catch (_) {
                this.habitualReferenceByWorker = {};
            }

            if (modalElement && window.bootstrap?.Modal) {
                this.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            }
            const workerSelect = modalElement?.querySelector('#temporary-assignment-worker');
            const dateSelect = modalElement?.querySelector('#temporary-assignment-date');
            dateSelect?.addEventListener('change', () => {
                this.updateEligibility();
                this.updateReference();
            });
            workerSelect?.addEventListener('change', () => this.updateReference());
            modalElement?.addEventListener('show.bs.modal', (event) => {
                this.error = '';
                this.status = '';
                this.completed = false;
                const workerId = String(event.relatedTarget?.dataset?.scheduleTemporaryWorkerId || '');
                if (workerId && workerSelect && dateSelect) {
                    const workerOption = Array.from(workerSelect.options).find((option) => option.value === workerId);
                    const eligibleDates = String(workerOption?.dataset?.verifiedDates || '').split(',').filter(Boolean);
                    if (eligibleDates.length > 0 && !eligibleDates.includes(dateSelect.value)) {
                        dateSelect.value = eligibleDates[0];
                        dateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                const selectedWorker = workerId && workerSelect
                    ? Array.from(workerSelect.options).find((option) => option.value === workerId)
                    : null;
                if (workerSelect) workerSelect.value = selectedWorker && !selectedWorker.disabled ? workerId : '';
                this.updateEligibility();
                this.updateReference();
            });
            modalElement?.addEventListener('hidden.bs.modal', () => this.reset());
        },

        updateEligibility() {
            const modalElement = document.querySelector('#temporary-schedule-assignment-modal');
            const workerSelect = modalElement?.querySelector('[data-schedule-worker-select]');
            const dateSelect = modalElement?.querySelector('[data-schedule-target-date]');
            const statusElement = modalElement?.querySelector('[data-schedule-worker-status]');
            const targetDate = dateSelect?.value || '';
            let eligibleCount = 0;

            Array.from(workerSelect?.options || []).forEach((option) => {
                if (!option.value) {
                    option.disabled = false;
                    return;
                }
                const verifiedDates = String(option.dataset.verifiedDates || '')
                    .split(',')
                    .filter(Boolean);
                const eligible = targetDate !== '' && verifiedDates.includes(targetDate);
                option.disabled = !eligible;
                option.textContent = option.dataset.workerLabel || option.textContent;
                if (eligible) eligibleCount++;

                if (option.selected && !eligible) {
                    workerSelect.value = '';
                }
            });

            if (!statusElement) return;
            this.workerEligibilityStatus = !targetDate
                ? 'Selecciona una fecha para consultar el personal con adscripción verificada.'
                : eligibleCount > 0
                    ? `${eligibleCount} trabajador${eligibleCount === 1 ? '' : 'es'} con membresía confirmada para ${targetDate}.`
                    : `Ningún trabajador tiene membresía confirmada para ${targetDate}.`;
            statusElement.textContent = this.workerEligibilityStatus;
        },

        updateReference() {
            const form = document.querySelector('#temporary-schedule-assignment-modal form');
            const workerId = form?.querySelector('[name="worker_id"]')?.value || '';
            const targetDate = form?.querySelector('[name="target_date"]')?.value || '';
            const reference = workerId && targetDate
                ? this.habitualReferenceByWorker?.[workerId]?.[targetDate]
                : null;
            const normalizedReference = String(reference || '').toLocaleLowerCase('es-MX');
            if (!workerId || !targetDate) {
                this.habitualReference = 'Selecciona un trabajador y una fecha para consultar el horario habitual.';
            } else if (!reference || normalizedReference.includes('sin asignación') || normalizedReference.includes('no hay información habitual')) {
                this.habitualReference = 'No hay horario habitual confirmado para esta fecha. El cambio sólo aplicará al día seleccionado; después se retomará el horario habitual vigente, si existe.';
            } else if (normalizedReference.includes('no disponible') || normalizedReference.includes('incompleta')) {
                this.habitualReference = 'No se pudo confirmar el horario habitual para esta fecha. El servidor volverá a validar la membresía y los conflictos antes de guardar.';
            } else {
                this.habitualReference = reference;
            }
        },

        async submit(event) {
            const form = event.currentTarget;
            if (this.loading || this.completed) return;
            this.error = '';
            this.status = '';
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            this.loading = true;
            try {
                const payload = new FormData(form);
                const seed = String(payload.get('idempotency_seed') || '');
                const workerId = String(payload.get('worker_id') || '');
                const targetDate = String(payload.get('target_date') || '');
                payload.set('idempotency_key', `${seed}:${workerId}:${targetDate}`);
                const response = await axios.post(form.action, payload, {
                    headers: { Accept: 'application/json' },
                });
                if (response.data?.ok !== true || ![200, 201].includes(response.status)) {
                    throw new Error(response.data?.message || 'El servidor no confirmó el cambio.');
                }
                this.completed = true;
                this.status = response.data.message || 'El cambio fue confirmado mediante lectura posterior.';
                window.setTimeout(() => window.location.reload(), 1000);
            } catch (requestError) {
                const responseData = requestError.response?.data;
                const responseStatus = Number(requestError.response?.status || 0);
                this.error = responseData?.message || assignmentErrorMessage(responseData?.code) || requestError.message || ({
                    401: 'La sesión expiró. Inicia sesión nuevamente.',
                    403: 'No tienes permiso para cambiar la programación de este centro.',
                    422: 'Revisa el trabajador, la fecha, el turno y la confirmación.',
                    502: 'No se pudo confirmar la lectura posterior de MariaDB; no se declara guardado.',
                }[responseStatus] || 'No fue posible guardar el cambio. La programación actual no se declara modificada.');
            } finally {
                this.loading = false;
            }
        },

        reset() {
            const form = document.querySelector('#temporary-schedule-assignment-modal form');
            form?.reset();
            this.loading = false;
            this.completed = false;
            this.error = '';
            this.status = '';
            this.workerEligibilityStatus = 'Selecciona una fecha para consultar el personal con adscripción verificada.';
            const dateSelect = form?.querySelector('[name="target_date"]');
            dateSelect?.dispatchEvent(new Event('change', { bubbles: true }));
            this.updateReference();
        },
    }));

    Alpine.data('habitualScheduleAssignment', () => ({
        workerId: '',
        workerName: '',
        existingDays: {},
        existingDaysByDate: {},
        dateScopedReadback: false,
        verifiedDates: [],
        loading: false,
        completed: false,
        effectiveDatesComplete: false,
        error: '',
        status: '',
        effectiveStartMessage: 'El patrón semanal se aplicará desde la fecha elegida; los días anteriores no generan asignaciones.',
        modal: null,
        readbackSequence: 0,

        initialize(element) {
            const modalElement = element.id === 'habitual-schedule-assignment-modal'
                ? element
                : element.querySelector('#habitual-schedule-assignment-modal');
            if (modalElement && window.bootstrap?.Modal) {
                this.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            }
            modalElement?.querySelector('[name="effective_start_date"]')?.addEventListener('change', () => {
                this.updateEffectiveStart();
                void this.loadReadback();
            });

            document.addEventListener('click', async (event) => {
                const button = event.target instanceof Element
                    ? event.target.closest('[data-habitual-assignment-read-url]')
                    : null;
                if (!button) return;
                event.preventDefault();
                if (this.loading) return;
                this.open(button);
                this.modal?.show();
                await this.loadReadback(button.dataset.habitualAssignmentReadUrl);
            });

            modalElement?.addEventListener('hidden.bs.modal', () => this.reset());
        },

        open(button) {
            this.workerId = button.dataset.habitualWorkerId || '';
            this.workerName = button.dataset.habitualWorkerName || '';
            this.existingDays = {};
            this.existingDaysByDate = {};
            this.dateScopedReadback = false;
            this.verifiedDates = [];
            this.completed = false;
            this.effectiveDatesComplete = false;
            this.error = '';
            this.status = 'Confirmando el historial de asignaciones…';
            const form = document.querySelector('#habitual-schedule-assignment-modal form');
            const effectiveInput = form?.querySelector('[name="effective_start_date"]');
            if (effectiveInput && !effectiveInput.value) effectiveInput.value = effectiveInput.min || '';
            document.querySelectorAll('#habitual-schedule-assignment-modal select[name^="schedule["]').forEach((select) => {
                select.disabled = true;
                select.value = '';
                select.options[0].textContent = 'Confirmando adscripción y asignaciones…';
            });
            this.updateEffectiveStart();
        },

        async loadReadback(url = '') {
            const form = document.querySelector('#habitual-schedule-assignment-modal form');
            const effectiveStart = form?.querySelector('[name="effective_start_date"]')?.value || '';
            const readUrl = url || document.querySelector('#habitual-schedule-assignment-modal')?.dataset?.assignmentReadUrl || '';
            if (!this.workerId || !effectiveStart || !readUrl) return;
            const sequence = ++this.readbackSequence;
            this.loading = true;
            this.existingDays = {};
            this.existingDaysByDate = {};
            this.dateScopedReadback = false;
            this.verifiedDates = [];
            this.effectiveDatesComplete = false;
            this.error = '';
            this.status = `Consultando las siete ocurrencias desde ${effectiveStart}…`;
            try {
                const response = await axios.get(readUrl, {
                    params: {
                        worker_id: this.workerId,
                        workplace_id: form.querySelector('[name="workplace_id"]')?.value,
                        week_start: form.querySelector('[name="week_start"]')?.value,
                        effective_start_date: effectiveStart,
                    },
                    headers: { Accept: 'application/json' },
                });
                if (sequence !== this.readbackSequence) return;
                const data = response.data?.data;
                if (response.data?.ok !== true || data?.complete !== true
                    || data?.read_scope !== 'EFFECTIVE_SEVEN_OCCURRENCES'
                    || data?.history_complete !== false || data?.effective_start_date !== effectiveStart
                    || !Array.isArray(data?.target_dates) || data.target_dates.length !== 7
                    || !Array.isArray(data?.verified_dates)
                    || !data?.existing_days_by_date || typeof data.existing_days_by_date !== 'object'
                    || typeof data?.habitual_complete !== 'boolean') {
                    throw new Error(response.data?.message || 'No se confirmaron las siete fechas y adscripciones efectivas.');
                }
                this.existingDays = data.existing_days && typeof data.existing_days === 'object' ? data.existing_days : {};
                this.existingDaysByDate = data.existing_days_by_date;
                this.dateScopedReadback = true;
                this.verifiedDates = data.verified_dates;
                const targetDatesMatch = Array.from(form.querySelectorAll('select[name^="schedule["]')).every((select) => {
                    const weekday = select.name.match(/^schedule\[([A-Z]+)\]$/)?.[1] || '';
                    const expected = this.dateForWeekday(weekday);
                    const actual = data.target_dates.find((item) => item?.weekday === weekday)?.date || '';
                    return expected !== '' && expected === actual;
                });
                if (!targetDatesMatch) throw new Error('Las fechas calculadas no coinciden con el readback SQL; no se habilitó el formulario.');
                const effectiveDates = data.target_dates.map((item) => item?.date || '');
                const uniqueEffectiveDates = new Set(effectiveDates);
                if (uniqueEffectiveDates.size !== 7 || effectiveDates.some((date) => !/^\d{4}-\d{2}-\d{2}$/.test(date))) {
                    throw new Error('El readback no contiene siete fechas efectivas distintas; no se habilitó el formulario.');
                }
                const verifiedDates = new Set(data.verified_dates);
                const effectiveDatesComplete = effectiveDates.every((date) => verifiedDates.has(date)
                    && Object.prototype.hasOwnProperty.call(data.existing_days_by_date, date));
                if (data.habitual_complete !== effectiveDatesComplete) {
                    throw new Error('La cobertura visible y las siete fechas efectivas no coinciden con el readback SQL; no se habilitó el formulario.');
                }
                this.effectiveDatesComplete = effectiveDatesComplete;
                this.error = '';
                this.status = '';

                if (effectiveDatesComplete) {
                    this.status = 'Las siete fechas efectivas desde el inicio seleccionado ya tienen asignación. No hay días pendientes ni se enviará un duplicado. Para cambiar un horario, selecciona expresamente «Cambiar horario habitual».';
                }
            } catch (requestError) {
                if (sequence !== this.readbackSequence) return;
                this.error = requestError.response?.data?.message
                    || ({ 401: 'La sesión expiró. Inicia sesión nuevamente.', 403: 'No puedes administrar a este trabajador en la estación seleccionada.', 409: 'Hay asignaciones incompatibles en las fechas seleccionadas.', 429: 'El servicio está ocupado. Espera un momento antes de reintentar.' }[Number(requestError.response?.status)]
                    || 'No se pudieron confirmar las siete fechas; no se habilitó el formulario.');
                this.status = '';
            } finally {
                if (sequence === this.readbackSequence) {
                    this.loading = false;
                    this.$nextTick(() => this.applyExistingDays());
                }
            }
        },

        applyExistingDays() {
            const form = document.querySelector('#habitual-schedule-assignment-modal form');
            form?.querySelectorAll('select[name^="schedule["]').forEach((select) => {
                const weekday = select.name.match(/^schedule\[([A-Z]+)\]$/)?.[1] || '';
                const date = this.dateForWeekday(weekday);
                const existingValue = this.dateScopedReadback
                    ? (Object.prototype.hasOwnProperty.call(this.existingDaysByDate, date) ? this.existingDaysByDate[date] : null)
                    : (Object.prototype.hasOwnProperty.call(this.existingDays, weekday) ? this.existingDays[weekday] : null);
                const alreadyAssigned = existingValue !== null;
                const verified = date !== '' && this.verifiedDates.includes(date);
                select.disabled = this.loading || !!this.error || !verified || alreadyAssigned;
                select.value = alreadyAssigned ? String(existingValue) : '';
                const effectiveLabel = select.closest('.col-12')?.querySelector('[data-habitual-day-effective]');
                if (effectiveLabel) effectiveLabel.textContent = date ? `Se aplicará desde ${date}` : 'Selecciona una fecha de inicio.';
                select.options[0].textContent = !verified
                    ? 'Adscripción no confirmada'
                    : (alreadyAssigned ? 'Asignación vigente · se conserva' : 'Selecciona turno o descanso explícito');
            });
        },

        async submit(event) {
            const form = event.currentTarget;
            if (this.loading || this.completed || this.effectiveDatesComplete) return;
            this.error = '';
            this.status = '';
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const pending = Array.from(form.querySelectorAll('select[name^="schedule["]'))
                .filter((select) => !select.disabled && select.value !== '')
                .map((select) => ({
                    targetDate: this.dateForWeekday(select.name.match(/^schedule\[([A-Z]+)\]$/)?.[1]),
                    templateId: select.value,
                }))
                .sort((left, right) => left.targetDate.localeCompare(right.targetDate));
            if (pending.length === 0) {
                this.error = 'Selecciona un turno o descanso explícito para al menos un día pendiente.';
                return;
            }

            this.loading = true;
            try {
                const modalElement = form.closest('#habitual-schedule-assignment-modal');
                const readUrl = modalElement?.dataset?.assignmentReadUrl || '';
                const preflight = await axios.get(readUrl, {
                    params: {
                        worker_id: this.workerId,
                        workplace_id: form.querySelector('[name="workplace_id"]')?.value,
                        week_start: form.querySelector('[name="week_start"]')?.value,
                        effective_start_date: form.querySelector('[name="effective_start_date"]')?.value,
                    },
                    headers: { Accept: 'application/json' },
                });
                const preflightData = preflight.data?.data;
                if (preflight.data?.ok !== true || preflightData?.complete !== true
                    || preflightData?.read_scope !== 'EFFECTIVE_SEVEN_OCCURRENCES'
                    || preflightData?.history_complete !== false
                    || !Array.isArray(preflightData?.verified_dates)
                    || !preflightData?.existing_days_by_date
                    || typeof preflightData.existing_days_by_date !== 'object'
                    || preflightData.effective_start_date !== form.querySelector('[name="effective_start_date"]')?.value
                    || !Array.isArray(preflightData.target_dates) || preflightData.target_dates.length !== 7) {
                    throw new Error(preflight.data?.message || 'No se confirmaron las siete fechas efectivas; no se enviaron asignaciones.');
                }
                const verifiedDates = new Set(preflightData.verified_dates);
                const existingByDate = preflightData.existing_days_by_date;
                const blockedDates = pending.filter((day) => !verifiedDates.has(day.targetDate)
                    || Object.prototype.hasOwnProperty.call(existingByDate, day.targetDate));
                if (blockedDates.length > 0) {
                    const dates = blockedDates.map((day) => day.targetDate).join(', ');
                    throw new Error(`No se envió el patrón. Revisa la adscripción o las asignaciones existentes para: ${dates}.`);
                }
                const payload = new FormData(form);
                payload.set('tipo_asignacion', 'HABITUAL');
                const seed = String(payload.get('idempotency_seed') || '');
                const workplaceId = String(payload.get('workplace_id') || '');
                const effectiveStart = String(payload.get('effective_start_date') || '');
                payload.set('idempotency_key', `${seed}:${this.workerId}:${workplaceId}:${effectiveStart}`);
                const response = await axios.post(form.action, payload, { headers: { Accept: 'application/json' } });
                if (response.data?.ok !== true || !Array.isArray(response.data?.assignments)
                    || response.data.assignments.length !== pending.length) {
                    throw new Error(response.data?.message || 'El servidor no confirmó las siete fechas seleccionadas mediante readback.');
                }
                this.status = `${response.data.assignments.length} día${response.data.assignments.length === 1 ? '' : 's'} confirmado${response.data.assignments.length === 1 ? '' : 's'} en una sola transacción. Actualizando la matriz…`;
                this.completed = true;
                window.setTimeout(() => window.location.reload(), 900);
            } catch (requestError) {
                this.error = requestError.response?.data?.message
                    || assignmentErrorMessage(requestError.response?.data?.code)
                    || requestError.message
                    || 'No se confirmó el patrón completo; actualiza la matriz antes de reintentar.';
            } finally {
                this.loading = false;
            }
        },

        dateForWeekday(weekday) {
            let dates = {};
            try {
                dates = JSON.parse(document.getElementById('habitual-week-dates')?.textContent || '{}');
            } catch (_) {
                return '';
            }
            const baseDate = dates[weekday] || '';
            const effectiveStart = document.querySelector('#habitual-schedule-assignment-modal [name="effective_start_date"]')?.value || '';
            if (!baseDate || !effectiveStart) return '';
            const target = new Date(`${baseDate}T00:00:00Z`);
            const start = new Date(`${effectiveStart}T00:00:00Z`);
            if (Number.isNaN(target.getTime()) || Number.isNaN(start.getTime())) return '';
            while (target < start) target.setUTCDate(target.getUTCDate() + 7);
            return target.toISOString().slice(0, 10);
        },

        updateEffectiveStart() {
            const effectiveStart = document.querySelector('#habitual-schedule-assignment-modal [name="effective_start_date"]')?.value || '';
            this.effectiveStartMessage = effectiveStart
                ? `El patrón comienza el ${effectiveStart}. Los días anteriores no generan asignaciones ni descansos.`
                : 'El patrón semanal se aplicará desde la fecha elegida; los días anteriores no generan asignaciones.';
            this.$nextTick(() => this.applyExistingDays());
        },

        reset() {
            this.workerId = '';
            this.workerName = '';
            this.existingDays = {};
            this.existingDaysByDate = {};
            this.dateScopedReadback = false;
            this.verifiedDates = [];
            this.error = '';
            this.status = '';
            this.loading = false;
            this.effectiveDatesComplete = false;
            this.readbackSequence++;
            this.completed = false;
            this.effectiveStartMessage = 'El patrón semanal se aplicará desde la fecha elegida; los días anteriores no generan asignaciones.';
            const effectiveInput = document.querySelector('#habitual-schedule-assignment-modal [name="effective_start_date"]');
            if (effectiveInput) effectiveInput.value = '';
        },
    }));

    Alpine.data('habitualScheduleReplacement', () => ({
        workerName: '',
        sources: [],
        submitting: false,
        dateInvalid: true,
        dateHint: 'La fecha debe coincidir con el día seleccionado y ser prospectiva.',

        initialize(element) {
            element.addEventListener('show.bs.modal', (event) => {
                this.submitting = false;
                this.dateInvalid = true;
                this.sources = [];
                this.workerName = String(event.relatedTarget?.dataset?.habitualReplacementWorkerName || '');
                try {
                    const parsed = JSON.parse(event.relatedTarget?.dataset?.habitualReplacementSources || '[]');
                    this.sources = Array.isArray(parsed) ? parsed.filter(item => item && Number(item.id) > 0) : [];
                } catch (_) {
                    this.sources = [];
                }

                const selector = this.$refs.source;
                selector.replaceChildren(new Option('Selecciona un día con horario confirmado', ''));
                this.sources.forEach((source, index) => {
                    const option = new Option(`${source.weekday_label || source.weekday} · ${source.label || 'Horario vigente'} · desde ${source.valid_from}`, String(index));
                    selector.add(option);
                });
                this.$refs.workerId.value = String(event.relatedTarget?.dataset?.habitualReplacementWorkerId || '');
                this.$refs.assignmentId.value = '';
                this.$refs.expectedFrom.value = '';
                this.$refs.expectedTo.value = '';
                this.$refs.selectedDate.value = '';
                this.$refs.effectiveDate.value = '';
                this.$refs.effectiveDate.removeAttribute('min');
                this.$refs.effectiveDate.removeAttribute('max');
                this.dateHint = 'La fecha debe coincidir con el día seleccionado y ser prospectiva.';
                selector.value = '';
            });

            element.querySelector('form')?.addEventListener('submit', (event) => {
                if (this.dateInvalid || this.submitting) {
                    event.preventDefault();
                    return;
                }
                this.submitting = true;
            });

            element.addEventListener('hidden.bs.modal', () => {
                this.submitting = false;
                this.sources = [];
                this.workerName = '';
                this.dateInvalid = true;
            });
        },

        selectSource() {
            const index = Number(this.$refs.source.value);
            const source = Number.isInteger(index) ? this.sources[index] : null;
            this.$refs.assignmentId.value = source ? String(source.id) : '';
            this.$refs.expectedFrom.value = source?.valid_from || '';
            this.$refs.expectedTo.value = source?.valid_to || '';
            this.$refs.effectiveDate.value = '';
            this.dateInvalid = true;
            if (!source) return;

            const tomorrow = new Date();
            tomorrow.setHours(0, 0, 0, 0);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const validFrom = new Date(`${source.valid_from}T00:00:00Z`);
            validFrom.setUTCDate(validFrom.getUTCDate() + 1);
            const start = new Date(Math.max(tomorrow.getTime(), validFrom.getTime()));
            const weekdayCodes = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
            const targetWeekday = String(source.weekday || '').toLocaleUpperCase('es-MX');
            for (let offset = 0; offset < 14; offset += 1) {
                const candidate = new Date(start);
                candidate.setUTCDate(candidate.getUTCDate() + offset);
                if (weekdayCodes[candidate.getUTCDay()] !== targetWeekday) continue;
                const date = candidate.toISOString().slice(0, 10);
                if (source.valid_to && date >= source.valid_to) break;
                this.$refs.effectiveDate.value = date;
                this.$refs.effectiveDate.min = tomorrow.toISOString().slice(0, 10);
                if (source.valid_to) {
                    const max = new Date(`${source.valid_to}T00:00:00Z`);
                    max.setUTCDate(max.getUTCDate() - 1);
                    this.$refs.effectiveDate.max = max.toISOString().slice(0, 10);
                }
                break;
            }
            this.dateHint = `Se sustituirá la versión de ${source.weekday_label || source.weekday}; el historial anterior permanecerá disponible.`;
            this.validateEffectiveDate();
        },

        validateEffectiveDate() {
            const source = this.sources[Number(this.$refs.source.value)] || null;
            const date = String(this.$refs.effectiveDate.value || '');
            const dateValue = date ? new Date(`${date}T00:00:00Z`) : null;
            const weekdayCodes = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
            const tomorrow = new Date();
            tomorrow.setHours(0, 0, 0, 0);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const targetWeekday = String(source?.weekday || '').toLocaleUpperCase('es-MX');
            const valid = source !== null && dateValue !== null && !Number.isNaN(dateValue.getTime())
                && dateValue.getTime() >= tomorrow.getTime()
                && date > String(source.valid_from || '')
                && (!source.valid_to || date < source.valid_to)
                && weekdayCodes[dateValue.getUTCDay()] === targetWeekday;
            this.dateInvalid = !valid;
            this.$refs.selectedDate.value = valid ? date : '';
            if (!valid) this.dateHint = 'Elige una fecha futura que coincida con el día seleccionado y esté dentro de la vigencia confirmada.';
        },
    }));
});
