/**
 * Checador V2 - Comparación con Nómina
 * Inicialización institucional de DataTables 1.13.5 y Modal de Búsqueda con Select2
 */
(function ($) {
    'use strict';

    function initPayrollComparisonSelect2() {
        if (!$.fn.select2) return;

        var $modal = $('#checador-payroll-search-modal');
        var $workerFilter = $('#comparison-worker-filter');

        if ($workerFilter.length && !$workerFilter.hasClass('select2-hidden-accessible')) {
            $workerFilter.select2({
                placeholder: 'Todos los colaboradores',
                allowClear: true,
                width: '100%',
                dropdownParent: $modal.length ? $modal : null,
                language: {
                    noResults: function () {
                        return 'No se encontraron colaboradores';
                    },
                    searching: function () {
                        return 'Buscando colaboradores...';
                    }
                }
            });
        }
    }

    function initPayrollComparisonDataTable() {
        if (!$.fn.DataTable) return;

        var $table = $('#checador-payroll-table');
        if (!$table.length) return;

        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        $table.DataTable({
            autoWidth: false,
            responsive: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50],
            order: [[0, 'asc']],
            dom: "<'row mb-3 align-items-center'<'col-12 col-md-6'l><'col-12 col-md-6'>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row mt-3 align-items-center'<'col-12 col-md-6'i><'col-12 col-md-6 d-flex justify-content-center justify-content-md-end'p>>",
            columnDefs: [
                {
                    targets: 7, // Columna Acciones
                    orderable: false,
                    searchable: false,
                    className: 'table-actions-cell text-center'
                }
            ],
            language: {
                processing: 'Procesando...',
                lengthMenu: 'Mostrar _MENU_ colaboradores',
                zeroRecords: 'No se encontraron colaboradores que coincidan con la búsqueda.',
                emptyTable: 'No se encontraron registros de comparación para los filtros seleccionados.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ colaboradores',
                infoEmpty: 'Mostrando 0 a 0 de 0 colaboradores',
                infoFiltered: '(filtrado de un total de _MAX_ colaboradores)',
                loadingRecords: 'Cargando información...',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function initFilterModalHandlers() {
        var $periodSelect = $('#comparison-period-select');
        var $datesText = $('#comparison-period-dates-text');

        if ($periodSelect.length && $datesText.length) {
            $periodSelect.on('change', function () {
                var $selected = $(this).find('option:selected');
                var start = $selected.data('start');
                var end = $selected.data('end');
                var label = $selected.text().trim();
                if (start && end) {
                    $datesText.text(start + ' al ' + end + ' (' + label + ')');
                }
            });
        }

        var $resetBtn = $('#payroll-reset-filters-btn');
        if ($resetBtn.length) {
            $resetBtn.on('click', function () {
                var currentYear = new Date().getFullYear();
                $('#comparison-year').val(currentYear).trigger('change');
                $('#comparison-period-select').prop('selectedIndex', 0).trigger('change');
                if ($.fn.select2 && $('#comparison-worker-filter').hasClass('select2-hidden-accessible')) {
                    $('#comparison-worker-filter').val('').trigger('change');
                } else {
                    $('#comparison-worker-filter').val('');
                }
                $('#comparison-status-filter').val('').trigger('change');
            });
        }
    }

    $(document).ready(function () {
        initPayrollComparisonSelect2();
        initPayrollComparisonDataTable();
        initFilterModalHandlers();
    });

})(window.jQuery);
