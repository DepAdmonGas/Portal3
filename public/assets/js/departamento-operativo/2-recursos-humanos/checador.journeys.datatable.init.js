(function () {
    'use strict';

    function initializeWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;

        const $ = window.jQuery;
        modal.querySelectorAll('[data-journey-worker-select]').forEach(function (select) {
            const $select = $(select);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    width: '100%',
                    language: 'es',
                    minimumResultsForSearch: 0,
                    dropdownParent: $(modal),
                    placeholder: 'Todos',
                    allowClear: true
                });
            }
            $select.css('width', '100%');
            $select.next('.select2-container').css('width', '100%');
        });
    }

    function destroyWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;

        const $ = window.jQuery;
        modal.querySelectorAll('[data-journey-worker-select]').forEach(function (select) {
            const $select = $(select);
            if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        });
    }

    function bindModalWorkerSelect2(modal) {
        if (!modal || modal.dataset.journeySelect2Bound === '1') return;
        modal.dataset.journeySelect2Bound = '1';
        modal.addEventListener('shown.bs.modal', function () { initializeWorkerSelect2(modal); });
        modal.addEventListener('hidden.bs.modal', function () { destroyWorkerSelect2(modal); });
        if (modal.classList.contains('show')) initializeWorkerSelect2(modal);
    }

    function initJourneyFilters(table) {
        const modal = document.querySelector('[data-journey-search-modal]');
        const form = modal && modal.querySelector('[data-journey-filter-form]');
        const worker = modal && modal.querySelector('[data-journey-worker-select]');
        const start = modal && modal.querySelector('[data-journey-date-start]');
        const end = modal && modal.querySelector('[data-journey-date-end]');
        if (!modal || !form) return;
        bindModalWorkerSelect2(modal);

        if (end && start) {
            form.addEventListener('submit', function (event) {
                end.setCustomValidity('');
                if (!start.value || !end.value) return;
                const startTime = Date.parse(start.value + 'T00:00:00Z');
                const endTime = Date.parse(end.value + 'T00:00:00Z');
                const rangeDays = Math.floor((endTime - startTime) / 86400000) + 1;
                if (Number.isFinite(rangeDays) && rangeDays >= 1 && rangeDays <= 31) return;
                end.setCustomValidity(rangeDays > 31
                    ? 'El rango no puede superar 31 fechas laborales.'
                    : 'La fecha final debe ser igual o posterior a la fecha inicial.');
                event.preventDefault();
                end.reportValidity();
            });
            [start, end].forEach(function (input) {
                input.addEventListener('change', function () { end.setCustomValidity(''); });
            });
        }

        if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            const $ = window.jQuery;
            let dataTable = null;
            if (!$.fn.DataTable.isDataTable(table)) {
                dataTable = $(table).DataTable({
                    pageLength: 25,
                    lengthMenu: [10, 25, 50],
                    order: [[1, 'desc']],
                    columnDefs: [{ targets: 5, orderable: false, searchable: false }],
                    language: {
                        emptyTable: 'No hay resultados de Jornada en esta consulta.',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ jornadas',
                        infoEmpty: 'Sin jornadas',
                        infoFiltered: '(filtrado de _MAX_ jornadas cargadas)',
                        lengthMenu: 'Mostrar _MENU_ jornadas',
                        loadingRecords: 'Cargando…',
                        processing: 'Procesando…',
                        search: 'Buscar en resultados cargados:',
                        zeroRecords: 'No se encontraron jornadas en los resultados cargados',
                        paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                        aria: { sortAscending: ': ordenar ascendente', sortDescending: ': ordenar descendente' }
                    }
                });
            } else {
                dataTable = $(table).DataTable();
            }
            form.addEventListener('reset', function () {
                if (end) end.setCustomValidity('');
                window.setTimeout(function () {
                    if (worker) $(worker).val('').trigger('change');
                }, 0);
            });
        }
    }

    function initialize() {
        const table = document.querySelector('[data-checador-journeys]');
        initJourneyFilters(table);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
