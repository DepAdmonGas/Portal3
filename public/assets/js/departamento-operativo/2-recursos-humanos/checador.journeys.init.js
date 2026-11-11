/**
 * Checador V2 - M05 Jornadas laborales frontend initialization
 * DataTables 1.13.5 and Select2 initialization
 */
(function ($) {
    'use strict';

    function initJourneysSelect2() {
        if (!$.fn.select2) return;

        var $modal = $('#checador-journeys-search-modal');
        var modalParent = $modal.length ? $modal : null;

        var $workerFilter = $('#journey-worker-filter');
        if ($workerFilter.length && !$workerFilter.hasClass('select2-hidden-accessible')) {
            $workerFilter.select2({
                placeholder: 'Todos los colaboradores',
                allowClear: true,
                width: '100%',
                dropdownParent: modalParent,
                dropdownCssClass: 'journey-filter-dropdown',
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

        var $statusFilter = $('#journey-status-filter');
        if ($statusFilter.length && !$statusFilter.hasClass('select2-hidden-accessible')) {
            $statusFilter.select2({
                placeholder: 'Todos los estados',
                allowClear: true,
                width: '100%',
                dropdownParent: modalParent,
                dropdownCssClass: 'journey-filter-dropdown',
                minimumResultsForSearch: 10,
                language: {
                    noResults: function () {
                        return 'No se encontraron estados';
                    }
                }
            });
        }
    }

    function initJourneysFilterModal() {
        var $modal = $('#checador-journeys-search-modal');
        var $form = $('#journeys-filter-form');
        if (!$modal.length || !$form.length) return;

        // Track committed state from page load / query params
        var committedValues = {
            date_from: $('#journey-date-from').val() || '',
            date_to: $('#journey-date-to').val() || '',
            worker_id: $('#journey-worker-filter').val() || '',
            status: $('#journey-status-filter').val() || ''
        };

        function restoreCommittedValues() {
            $('#journey-date-from').val(committedValues.date_from);
            $('#journey-date-to').val(committedValues.date_to);

            if ($.fn.select2 && $('#journey-worker-filter').hasClass('select2-hidden-accessible')) {
                $('#journey-worker-filter').val(committedValues.worker_id).trigger('change.select2');
            } else {
                $('#journey-worker-filter').val(committedValues.worker_id);
            }

            if ($.fn.select2 && $('#journey-status-filter').hasClass('select2-hidden-accessible')) {
                $('#journey-status-filter').val(committedValues.status).trigger('change.select2');
            } else {
                $('#journey-status-filter').val(committedValues.status);
            }
        }

        // On modal hide/cancel without submit, restore draft edits to committed state
        $modal.on('hidden.bs.modal', function () {
            restoreCommittedValues();
        });

        // "Limpiar filtros" button: resets modal inputs without submitting
        var $resetBtn = $('#journeys-reset-filters-btn');
        if ($resetBtn.length) {
            $resetBtn.on('click', function () {
                var today = new Date();
                var yyyy = today.getFullYear();
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var dd = String(today.getDate()).padStart(2, '0');
                var todayStr = yyyy + '-' + mm + '-' + dd;

                $('#journey-date-from').val(todayStr);
                $('#journey-date-to').val(todayStr);

                if ($.fn.select2 && $('#journey-worker-filter').hasClass('select2-hidden-accessible')) {
                    $('#journey-worker-filter').val('').trigger('change.select2');
                } else {
                    $('#journey-worker-filter').val('');
                }

                if ($.fn.select2 && $('#journey-status-filter').hasClass('select2-hidden-accessible')) {
                    $('#journey-status-filter').val('').trigger('change.select2');
                } else {
                    $('#journey-status-filter').val('');
                }
            });
        }
    }

    function initJourneysDataTable() {
        if (!$.fn.DataTable) return;

        var $table = $('#checador-journeys-table');
        if (!$table.length) return;

        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        $table.DataTable({
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc'], [1, 'asc']],
            dom: "<'row mb-3 align-items-center'<'col-12 col-md-6'l><'col-12 col-md-6'>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row mt-3 align-items-center'<'col-12 col-md-6'i><'col-12 col-md-6 d-flex justify-content-center justify-content-md-end'p>>",
            columnDefs: [
                {
                    targets: 7, // Columna Acciones
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }
            ],
            language: {
                processing: 'Procesando...',
                lengthMenu: 'Mostrar _MENU_ jornadas',
                zeroRecords: 'No se encontraron jornadas que coincidan con la búsqueda.',
                emptyTable: 'No se encontraron jornadas laborales para los filtros seleccionados.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ jornadas',
                infoEmpty: 'Mostrando 0 a 0 de 0 jornadas',
                infoFiltered: '(filtrado de un total de _MAX_ jornadas)',
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

    $(document).ready(function () {
        initJourneysSelect2();
        initJourneysFilterModal();
        initJourneysDataTable();
    });

})(window.jQuery);
