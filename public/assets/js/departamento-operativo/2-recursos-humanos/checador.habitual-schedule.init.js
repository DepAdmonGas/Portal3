document.addEventListener('alpine:init', () => {
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
                const response = await axios.post(form.action, new FormData(form), {
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
                this.error = responseData?.message || requestError.message || ({
                    401: 'La sesión expiró. Inicia sesión nuevamente.',
                    403: 'No tienes permiso para cambiar la programación de este centro.',
                    422: 'Revisa el trabajador, la fecha, el turno y la confirmación.',
                    502: 'No se pudo confirmar el readback de la API; no se declara guardado.',
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
        error: '',
        status: '',
        effectiveStartMessage: 'El patrón semanal se aplicará desde la fecha elegida; los días anteriores no generan asignaciones.',
        modal: null,

        initialize(element) {
            const modalElement = element.id === 'habitual-schedule-assignment-modal'
                ? element
                : element.querySelector('#habitual-schedule-assignment-modal');
            if (modalElement && window.bootstrap?.Modal) {
                this.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            }
            modalElement?.querySelector('[name="effective_start_date"]')?.addEventListener('change', () => {
                this.updateEffectiveStart();
            });

            document.addEventListener('click', async (event) => {
                const button = event.target instanceof Element
                    ? event.target.closest('[data-habitual-assignment-read-url]')
                    : null;
                if (!button) return;
                event.preventDefault();
                if (this.loading) return;
                this.open(button);
                this.loading = true;
                this.status = 'Confirmando el historial de asignaciones…';
                this.modal?.show();
                try {
                    const response = await axios.get(button.dataset.habitualAssignmentReadUrl, {
                        params: {
                            worker_id: button.dataset.habitualWorkerId,
                            workplace_id: button.dataset.habitualWorkplaceId,
                            week_start: button.dataset.habitualWeekStart,
                        },
                        headers: { Accept: 'application/json' },
                    });
                    const data = response.data?.data;
                    if (response.data?.ok !== true || data?.complete !== true) {
                        throw new Error(response.data?.message || 'No se confirmó la lectura completa.');
                    }
                    this.existingDays = data.existing_days && typeof data.existing_days === 'object' ? data.existing_days : {};
                    this.existingDaysByDate = data.existing_days_by_date && typeof data.existing_days_by_date === 'object'
                        ? data.existing_days_by_date
                        : {};
                    this.dateScopedReadback = Object.prototype.hasOwnProperty.call(data, 'existing_days_by_date');
                    this.verifiedDates = Array.isArray(data.verified_dates) ? data.verified_dates : [];
                    this.error = '';
                    this.status = '';

                    if (data.habitual_complete === true) {
                        const sources = Array.isArray(data.replacement_sources) ? data.replacement_sources : [];
                        if (sources.length !== 7) {
                            this.error = 'El horario ya está completo, pero no se pudo confirmar cada asignación vigente para cambiarlo.';
                            return;
                        }
                        const replacementWorkerId = this.workerId;
                        const replacementWorkerName = this.workerName;
                        this.modal?.hide();
                        this.$nextTick(() => {
                            const replacementTrigger = document.createElement('button');
                            replacementTrigger.type = 'button';
                            replacementTrigger.dataset.bsToggle = 'modal';
                            replacementTrigger.dataset.bsTarget = '#habitual-schedule-replacement-modal';
                            replacementTrigger.dataset.habitualReplacementWorkerId = replacementWorkerId;
                            replacementTrigger.dataset.habitualReplacementWorkerName = replacementWorkerName;
                            replacementTrigger.dataset.habitualReplacementSources = JSON.stringify(sources);
                            replacementTrigger.hidden = true;
                            document.body.append(replacementTrigger);
                            replacementTrigger.click();
                            replacementTrigger.remove();
                        });
                        return;
                    }

                    this.$nextTick(() => this.applyExistingDays());
                } catch (requestError) {
                    this.error = requestError.response?.data?.message
                        || ({ 401: 'La sesión expiró. Inicia sesión nuevamente.', 403: 'No puedes administrar a este trabajador en la estación seleccionada.', 429: 'El servicio está ocupado. Espera un momento antes de reintentar.' }[Number(requestError.response?.status)]
                        || 'No se pudo confirmar el historial. No se habilitó el formulario.');
                    this.status = '';
                } finally {
                    this.loading = false;
                    this.$nextTick(() => this.applyExistingDays());
                }
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
            this.error = '';
            this.status = 'Confirmando el historial de asignaciones…';
            this.$nextTick(() => {
                const form = document.querySelector('#habitual-schedule-assignment-modal form');
                const effectiveInput = form?.querySelector('[name="effective_start_date"]');
                if (effectiveInput && !effectiveInput.value) effectiveInput.value = effectiveInput.min || '';
                document.querySelectorAll('#habitual-schedule-assignment-modal select[name^="schedule["]').forEach((select) => {
                    select.disabled = true;
                    select.value = '';
                    select.options[0].textContent = 'Confirmando asignaciones…';
                });
                this.updateEffectiveStart();
            });
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
            if (this.loading) return;
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
            let confirmed = 0;
            let stopped = false;
            try {
                const modalElement = form.closest('#habitual-schedule-assignment-modal');
                const readUrl = modalElement?.dataset?.assignmentReadUrl || '';
                const preflight = await axios.get(readUrl, {
                    params: {
                        worker_id: this.workerId,
                        workplace_id: form.querySelector('[name="workplace_id"]')?.value,
                        week_start: form.querySelector('[name="week_start"]')?.value,
                    },
                    headers: { Accept: 'application/json' },
                });
                const preflightData = preflight.data?.data;
                if (preflight.data?.ok !== true || preflightData?.complete !== true
                    || !Array.isArray(preflightData?.verified_dates)
                    || !preflightData?.existing_days_by_date
                    || typeof preflightData.existing_days_by_date !== 'object') {
                    throw new Error(preflight.data?.message || 'No se confirmó el historial completo; no se enviaron asignaciones.');
                }
                const verifiedDates = new Set(preflightData.verified_dates);
                const existingByDate = preflightData.existing_days_by_date;
                const blockedDates = pending.filter((day) => !verifiedDates.has(day.targetDate)
                    || Object.prototype.hasOwnProperty.call(existingByDate, day.targetDate));
                if (blockedDates.length > 0) {
                    const dates = blockedDates.map((day) => day.targetDate).join(', ');
                    throw new Error(`No se guardó ningún día. Revisa la membresía o la asignación existente para: ${dates}.`);
                }

                for (const day of pending) {
                    const payload = new FormData(form);
                    for (const key of Array.from(payload.keys())) {
                        if (key.startsWith('schedule[')) payload.delete(key);
                    }
                    payload.set('target_date', day.targetDate);
                    payload.set('template_id', day.templateId);
                    payload.set('tipo_asignacion', 'HABITUAL');

                    try {
                        const response = await axios.post(form.action, payload, {
                            headers: { Accept: 'application/json' },
                        });
                        if (response.data?.ok !== true || ![200, 201].includes(response.status)) {
                            throw new Error(response.data?.message || 'El servidor no confirmó la asignación.');
                        }
                        confirmed += 1;
                    } catch (requestError) {
                        const responseData = requestError.response?.data;
                        this.error = responseData?.message || requestError.message
                            || 'No se pudo confirmar un día; los días previos confirmados se conservaron.';
                        stopped = true;
                        break;
                    }
                }

                if (!stopped && confirmed === pending.length) {
                    this.status = `${confirmed} día${confirmed === 1 ? '' : 's'} confirmado${confirmed === 1 ? '' : 's'} mediante readback. Actualizando la matriz…`;
                    window.setTimeout(() => window.location.reload(), 900);
                } else if (confirmed > 0) {
                    this.status = `${confirmed} ${confirmed === 1 ? 'día sí quedó confirmado' : 'días sí quedaron confirmados'}; los demás no se declaran guardados. Actualizando la matriz…`;
                    window.setTimeout(() => window.location.reload(), 1800);
                }
            } catch (requestError) {
                this.error = requestError.response?.data?.message || requestError.message
                    || 'No fue posible completar la validación previa; no se enviaron nuevas asignaciones.';
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
