(function () {
    'use strict';

    function initializeScheduleTemplateForm(form) {
        const entry = form.querySelector('[data-schedule-entry]');
        const breakStart = form.querySelector('[data-schedule-break-start]');
        const breakEnd = form.querySelector('[data-schedule-break-end]');
        const exit = form.querySelector('[data-schedule-exit]');
        const container = form.querySelector('[data-schedule-segment-container]');
        const typeInput = form.querySelector('[data-schedule-template-type]');
        const sourceTypeInput = form.querySelector('[data-schedule-source-type]');
        if (!entry || !breakStart || !breakEnd || !exit || !container || !typeInput) return;
        if (form.dataset.scheduleTemplateInitialized === 'true') return;
        form.dataset.scheduleTemplateInitialized = 'true';

        function clearGeneratedSegments() {
            container.replaceChildren();
        }

        function minuteOfDay(value) {
            if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(value || '')) return null;
            return Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
        }

        function reportInvalid(input, message) {
            input.setCustomValidity(message);
            input.reportValidity();
        }

        [entry, breakStart, breakEnd, exit].forEach((input) => {
            input.addEventListener('input', () => input.setCustomValidity(''));
            input.addEventListener('change', () => input.setCustomValidity(''));
        });

        form.addEventListener('submit', function (event) {
            clearGeneratedSegments();
            [entry, breakStart, breakEnd, exit].forEach((input) => input.setCustomValidity(''));
            const hasBreakStart = breakStart.value !== '';
            const hasBreakEnd = breakEnd.value !== '';
            if (hasBreakStart !== hasBreakEnd) {
                const incomplete = hasBreakStart ? breakEnd : breakStart;
                reportInvalid(incomplete, 'Indica las dos horas del descanso o deja ambas vacías.');
                event.preventDefault();
                return;
            }

            const checkpoints = [{ time: entry.value, input: entry }];
            if (hasBreakStart) {
                checkpoints.push({ time: breakStart.value, input: breakStart });
                checkpoints.push({ time: breakEnd.value, input: breakEnd });
            }
            checkpoints.push({ time: exit.value, input: exit });
            const minutes = checkpoints.map((point) => minuteOfDay(point.time));
            if (minutes.some((minute) => minute === null)) {
                const invalid = checkpoints.find((point, index) => minutes[index] === null);
                reportInvalid(invalid.input, 'Indica una hora válida.');
                event.preventDefault();
                return;
            }

            const offsets = [0];
            for (let index = 1; index < minutes.length; index += 1) {
                const offset = offsets[index - 1] + (minutes[index] <= minutes[index - 1] ? 1 : 0);
                if (offset > 1) {
                    reportInvalid(checkpoints[index].input, 'El turno no puede exceder el día siguiente.');
                    event.preventDefault();
                    return;
                }
                offsets.push(offset);
            }

            const kinds = hasBreakStart ? ['WORK', 'BREAK', 'WORK'] : ['WORK'];
            const startIndexes = hasBreakStart ? [0, 1, 2] : [0];
            const endIndexes = hasBreakStart ? [1, 2, 3] : [1];
            const segments = kinds.map((kind, index) => ({
                tipo_segmento: kind,
                hora_inicio_local: checkpoints[startIndexes[index]].time,
                hora_fin_local: checkpoints[endIndexes[index]].time,
                dia_offset_inicio: offsets[startIndexes[index]],
                dia_offset_fin: offsets[endIndexes[index]]
            }));
            segments.forEach((segment, index) => {
                Object.entries(segment).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `segments[${index}][${key}]`;
                    input.value = String(value);
                    container.appendChild(input);
                });
            });

            const overnight = offsets.some((offset) => offset === 1);
            const sourceType = sourceTypeInput ? sourceTypeInput.value : '';
            typeInput.value = overnight ? 'NOCTURNO' : (['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'].includes(sourceType) ? sourceType : 'REGULAR');
        });

        const createModal = form.closest('#station-template-create-modal');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                const isPrefill = trigger instanceof HTMLElement && trigger.hasAttribute('data-schedule-template-prefill');
                const name = form.querySelector('[name="template_name"]');
                const title = createModal.querySelector('#station-template-create-title');
                const confirmation = form.querySelector('[data-schedule-confirmation]');
                const submit = form.querySelector('[data-schedule-template-submit]');
                form.reset();
                clearGeneratedSegments();
                if (sourceTypeInput) sourceTypeInput.value = '';
                if (isPrefill) {
                    let schedule = null;
                    try {
                        schedule = JSON.parse(trigger.dataset.templateSchedule || 'null');
                    } catch (_) {
                        schedule = null;
                    }
                    if (!schedule || typeof schedule !== 'object' || !schedule.entrada || !schedule.salida) return;
                    if (name) name.value = trigger.dataset.templateName || '';
                    entry.value = schedule.entrada;
                    breakStart.value = schedule.descanso_inicio || '';
                    breakEnd.value = schedule.descanso_fin || '';
                    exit.value = schedule.salida;
                    const sourceType = trigger.dataset.templateType || '';
                    if (sourceTypeInput) sourceTypeInput.value = sourceType;
                    if (title) title.textContent = 'Personalizar turno para esta estación';
                    if (confirmation) confirmation.textContent = 'Se creará una plantilla nueva y exclusiva para la estación seleccionada. La plantilla global y otras estaciones no se modificarán.';
                    if (submit) submit.textContent = 'Crear personalización';
                } else {
                    if (title) title.textContent = 'Nueva plantilla para esta estación';
                    if (confirmation) confirmation.textContent = 'Se creará una plantilla nueva y exclusiva para la estación seleccionada. Las plantillas globales y otras estaciones no se modificarán.';
                    if (submit) submit.textContent = 'Guardar plantilla';
                }
            });
        }
    }

    function initializeAssignmentEligibility(form) {
        const workerSelect = form.querySelector('[data-schedule-worker-select]');
        const dateSelect = form.querySelector('[data-schedule-target-date]');
        const status = form.querySelector('[data-schedule-worker-status]');
        if (!workerSelect || !dateSelect) return;

        function synchronizeWorkers() {
            const selectedDate = dateSelect.value;
            let eligibleCount = 0;
            workerSelect.querySelectorAll('option[value]:not([value=""])').forEach((option) => {
                const verifiedDates = (option.dataset.verifiedDates || '').split(',').filter(Boolean);
                const eligible = verifiedDates.includes(selectedDate);
                option.disabled = !eligible;
                if (eligible) eligibleCount += 1;
            });
            const selectedOption = workerSelect.selectedOptions[0];
            if (selectedOption && selectedOption.disabled) workerSelect.value = '';
            if (status) {
                status.textContent = !selectedDate
                    ? 'Selecciona una fecha para consultar la pertenencia del personal.'
                    : eligibleCount > 0
                        ? 'La elegibilidad se verifica para el día seleccionado.'
                        : 'No hay trabajadores con pertenencia verificada para este día.';
            }
        }

        dateSelect.addEventListener('change', synchronizeWorkers);
        synchronizeWorkers();
    }

    document.querySelectorAll('form[data-schedule-template-form]').forEach(initializeScheduleTemplateForm);
    document.querySelectorAll('form[action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar"]')
        .forEach(initializeAssignmentEligibility);
})();
