(function () {
    'use strict';

    function initializeScheduleTemplateForm(form) {
        const container = form.querySelector('[data-schedule-segment-container]');
        const template = form.parentElement.querySelector('template[data-schedule-segment-template]');
        const addButton = form.querySelector('[data-schedule-add-segment]');
        if (!container || !template || !addButton) return;
        let nextIndex = container.querySelectorAll('[data-schedule-segment-row]').length;

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
            const index = nextIndex++;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[id], [for], [name]').forEach((element) => {
                ['id', 'for', 'name'].forEach((attribute) => {
                    if (element.hasAttribute(attribute)) {
                        element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(index)));
                    }
                });
            });
            container.appendChild(fragment);
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
