(function () {
    'use strict';

    function initSelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;
        const $ = window.jQuery;
        modal.querySelectorAll('[data-payroll-worker-select]').forEach(function (select) {
            const $select = $(select);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    width: '100%',
                    language: 'es',
                    minimumResultsForSearch: 0,
                    dropdownParent: $(modal),
                    placeholder: 'Selecciona un trabajador'
                });
            }
            $select.css('width', '100%');
            $select.next('.select2-container').css('width', '100%');
        });
    }

    function destroySelect2(modal) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !modal) return;
        modal.querySelectorAll('[data-payroll-worker-select]').forEach(function (select) {
            const $select = window.jQuery(select);
            if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        });
    }

    function initialize() {
        const modal = document.querySelector('[data-payroll-select2-modal]');
        if (modal && modal.dataset.payrollSelect2Bound !== '1') {
            modal.dataset.payrollSelect2Bound = '1';
            modal.addEventListener('shown.bs.modal', function () { initSelect2(modal); });
            modal.addEventListener('hidden.bs.modal', function () { destroySelect2(modal); });
            if (modal.classList.contains('show')) initSelect2(modal);
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            window.jQuery('[data-payroll-comparison-table]').each(function () {
                if (window.jQuery.fn.DataTable.isDataTable(this)) return;
                window.jQuery(this).DataTable({
                    pageLength: 25,
                    lengthMenu: [10, 25, 50],
                    order: [],
                    language: {
                        emptyTable: 'No hay registros para mostrar.',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                        infoEmpty: 'Sin registros',
                        infoFiltered: '(filtrado de _MAX_ registros)',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        loadingRecords: 'Cargando…',
                        processing: 'Procesando…',
                        search: 'Buscar en resultados:',
                        zeroRecords: 'No se encontraron registros',
                        paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                        aria: { sortAscending: ': ordenar ascendente', sortDescending: ': ordenar descendente' }
                    }
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
