(function () {
    'use strict';

    function initializeWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;

        const $ = window.jQuery;
        modal.querySelectorAll('[data-m06-worker-select]').forEach(function (select) {
            const $select = $(select);
            if (!$select.hasClass('select2-hidden-accessible')) {
                const isFilter = select.getAttribute('data-m06-worker-select') === 'filter';
                $select.select2({
                    width: '100%',
                    language: 'es',
                    minimumResultsForSearch: 0,
                    dropdownParent: $(modal),
                    placeholder: isFilter ? 'Todos' : 'Selecciona un trabajador',
                    allowClear: isFilter
                });
            }
            $select.css('width', '100%');
            $select.next('.select2-container').css('width', '100%');
        });
    }

    function destroyWorkerSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;

        const $ = window.jQuery;
        modal.querySelectorAll('[data-m06-worker-select]').forEach(function (select) {
            const $select = $(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.off('.m06IncidenceSelect2');
                $select.select2('destroy');
            }
        });
    }

    function bindModalWorkerSelect2(modal) {
        if (!modal || modal.dataset.m06Select2Bound === '1') return;
        modal.dataset.m06Select2Bound = '1';
        modal.addEventListener('shown.bs.modal', function () {
            initializeWorkerSelect2(modal);
        });
        modal.addEventListener('hidden.bs.modal', function () {
            destroyWorkerSelect2(modal);
        });
        if (modal.classList.contains('show')) initializeWorkerSelect2(modal);
    }

    function initializeM06Incidences() {
        const table = document.querySelector('[data-checador-m06-incidences]');
        if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable
            && !window.jQuery.fn.DataTable.isDataTable(table)) {
            window.jQuery(table).DataTable({
                pageLength: 25,
                lengthMenu: [10, 25, 50],
                order: [[4, 'desc']],
                columnDefs: [{ targets: 5, orderable: false, searchable: false }],
                language: {
                    emptyTable: 'No hay incidencias en este resultado.',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ incidencias',
                    infoEmpty: 'Sin incidencias',
                    infoFiltered: '(filtrado de _MAX_ incidencias)',
                    lengthMenu: 'Mostrar _MENU_ incidencias',
                    loadingRecords: 'Cargando…',
                    processing: 'Procesando…',
                    search: 'Buscar en esta página:',
                    zeroRecords: 'No se encontraron incidencias',
                    paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                    aria: { sortAscending: ': ordenar ascendente', sortDescending: ': ordenar descendente' }
                }
            });
        }

        document.querySelectorAll('[data-incidence-tab]').forEach(function (action) {
            action.addEventListener('click', function () {
                const targetSelector = action.getAttribute('data-bs-target');
                const tab = action.getAttribute('data-incidence-tab');
                const modal = targetSelector ? document.querySelector(targetSelector) : null;
                if (!modal) return;
                modal.addEventListener('shown.bs.modal', function () {
                    const tabButton = modal.querySelector('[id$="-' + (tab === 'history' ? 'history' : 'detail') + '-tab"]');
                    if (tabButton && window.bootstrap && window.bootstrap.Tab) {
                        window.bootstrap.Tab.getOrCreateInstance(tabButton).show();
                    }
                }, { once: true });
            });
        });

        document.querySelectorAll('[data-m06-select2-modal]').forEach(bindModalWorkerSelect2);

        document.querySelectorAll('[data-m06-auto-open="1"]').forEach(function (modal) {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeM06Incidences, { once: true });
    } else {
        initializeM06Incidences();
    }
}());
