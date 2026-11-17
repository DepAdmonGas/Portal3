(function () {
    'use strict';

    function initializeScheduleTemplateForm(form) {
        const container = form.querySelector('[data-schedule-segment-container]');
        const template = form.parentElement.querySelector('template[data-schedule-segment-template]');
        const addButton = form.querySelector('[data-schedule-add-segment]');
        if (!container || !template || !addButton) return;
        let nextIndex = container.querySelectorAll('[data-schedule-segment-row]').length;

        function appendSegment(segment = {}) {
            const index = nextIndex++;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[id], [for], [name]').forEach((element) => {
                ['id', 'for', 'name'].forEach((attribute) => {
                    if (element.hasAttribute(attribute)) {
                        element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(index)));
                    }
                });
            });
            const row = fragment.querySelector('[data-schedule-segment-row]');
            if (row && Object.keys(segment).length > 0) {
                const type = row.querySelector('select[name*="[tipo_segmento]"]');
                const start = row.querySelector('input[name*="[hora_inicio_local]"]');
                const end = row.querySelector('input[name*="[hora_fin_local]"]');
                const startOffset = row.querySelector('select[name*="[dia_offset_inicio]"]');
                const endOffset = row.querySelector('select[name*="[dia_offset_fin]"]');
                if (type && ['WORK', 'BREAK'].includes(segment.tipo_segmento)) type.value = segment.tipo_segmento;
                if (start && /^\d{2}:\d{2}$/.test(segment.hora_inicio_local || '')) start.value = segment.hora_inicio_local;
                if (end && /^\d{2}:\d{2}$/.test(segment.hora_fin_local || '')) end.value = segment.hora_fin_local;
                if (startOffset && ['0', '1'].includes(String(segment.dia_offset_inicio ?? 0))) startOffset.value = String(segment.dia_offset_inicio ?? 0);
                if (endOffset && ['0', '1'].includes(String(segment.dia_offset_fin ?? 0))) endOffset.value = String(segment.dia_offset_fin ?? 0);
            }
            container.appendChild(fragment);
        }

        function setSegments(segments) {
            while (container.firstChild) container.removeChild(container.firstChild);
            nextIndex = 0;
            const usableSegments = Array.isArray(segments) ? segments.slice(0, 255) : [];
            if (usableSegments.length === 0) appendSegment();
            else usableSegments.forEach((segment) => appendSegment(segment && typeof segment === 'object' ? segment : {}));
            synchronizeControls();
        }

        function synchronizeControls() {
            const rows = container.querySelectorAll('[data-schedule-segment-row]');
            rows.forEach((row) => {
                const removeButton = row.querySelector('[data-schedule-remove-segment]');
                if (removeButton) removeButton.disabled = rows.length <= 1;
            });
            addButton.disabled = rows.length >= 255;
        }

        addButton.addEventListener('click', function () {
            const rows = container.querySelectorAll('[data-schedule-segment-row]');
            if (rows.length >= 255) return;
            appendSegment();
            synchronizeControls();
        });

        container.addEventListener('click', function (event) {
            const button = event.target instanceof Element
                ? event.target.closest('[data-schedule-remove-segment]')
                : null;
            if (!button || container.querySelectorAll('[data-schedule-segment-row]').length <= 1) return;
            const row = button.closest('[data-schedule-segment-row]');
            if (row) row.remove();
            synchronizeControls();
        });

        synchronizeControls();

        const modal = form.closest('#station-template-create-modal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                const prefill = trigger instanceof HTMLElement && trigger.hasAttribute('data-schedule-template-prefill');
                const name = form.querySelector('[name="template_name"]');
                const type = form.querySelector('[name="template_type"]');
                const title = modal.querySelector('#station-template-create-title');
                const confirmation = form.querySelector('[name="confirm_template"]')?.nextElementSibling;
                const submit = form.querySelector('[data-schedule-template-submit]');

                form.reset();
                setSegments([]);
                if (prefill) {
                    let segments = [];
                    try {
                        const parsed = JSON.parse(trigger.dataset.templateSegments || '[]');
                        if (Array.isArray(parsed)) segments = parsed;
                    } catch (_) {
                        segments = [];
                    }
                    if (name) name.value = trigger.dataset.templateName || '';
                    if (type && ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'].includes(trigger.dataset.templateType || '')) {
                        type.value = trigger.dataset.templateType;
                    }
                    setSegments(segments);
                    if (title) title.textContent = 'Personalizar turno para esta estación';
                    if (confirmation) confirmation.textContent = 'Se creará una plantilla nueva y exclusiva para la estación seleccionada. La plantilla global y otras estaciones no se modificarán.';
                    if (submit) submit.textContent = 'Crear personalización';
                } else {
                    if (title) title.textContent = 'Nueva plantilla para esta estación';
                    if (confirmation) confirmation.textContent = 'Confirmo crear esta plantilla exclusivamente para la estación seleccionada.';
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
