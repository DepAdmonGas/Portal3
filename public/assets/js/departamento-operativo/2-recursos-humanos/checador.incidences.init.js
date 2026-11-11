/**
 * Checador V2 - M06 Incidencias frontend initialization
 * DataTables 1.13.5 and Select2 initialization
 */
(function ($) {
    'use strict';

    function initIncidencesSelect2() {
        if (!$ || !$.fn.select2) return;

        var $modal = $('#checador-incidences-search-modal');
        var modalParent = $modal.length ? $modal : null;

        var $workerFilter = $('#incidence-worker-filter');
        if ($workerFilter.length && !$workerFilter.hasClass('select2-hidden-accessible')) {
            $workerFilter.select2({
                placeholder: 'Todos los colaboradores',
                allowClear: true,
                width: '100%',
                dropdownParent: modalParent,
                dropdownCssClass: 'incidence-filter-dropdown',
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

        var $typeFilter = $('#incidence-type-filter');
        if ($typeFilter.length && !$typeFilter.hasClass('select2-hidden-accessible')) {
            $typeFilter.select2({
                placeholder: 'Todos los tipos',
                allowClear: true,
                width: '100%',
                dropdownParent: modalParent,
                dropdownCssClass: 'incidence-filter-dropdown',
                minimumResultsForSearch: 10,
                language: {
                    noResults: function () {
                        return 'No se encontraron tipos';
                    }
                }
            });
        }

        var $statusFilter = $('#incidence-status-filter');
        if ($statusFilter.length && !$statusFilter.hasClass('select2-hidden-accessible')) {
            $statusFilter.select2({
                placeholder: 'Todos los estados',
                allowClear: true,
                width: '100%',
                dropdownParent: modalParent,
                dropdownCssClass: 'incidence-filter-dropdown',
                minimumResultsForSearch: 10,
                language: {
                    noResults: function () {
                        return 'No se encontraron estados';
                    }
                }
            });
        }
    }

    function initIncidencesFilterModal() {
        var $modal = $('#checador-incidences-search-modal');
        var $form = $('#incidences-filter-form');
        if (!$modal.length || !$form.length) return;

        // Track committed state from page load / query params
        var committedValues = {
            date_from: $('#incidence-date-from').val() || '',
            date_to: $('#incidence-date-to').val() || '',
            worker_id: $('#incidence-worker-filter').val() || '',
            type: $('#incidence-type-filter').val() || '',
            status: $('#incidence-status-filter').val() || ''
        };

        function restoreCommittedValues() {
            $('#incidence-date-from').val(committedValues.date_from);
            $('#incidence-date-to').val(committedValues.date_to);

            if ($.fn.select2 && $('#incidence-worker-filter').hasClass('select2-hidden-accessible')) {
                $('#incidence-worker-filter').val(committedValues.worker_id).trigger('change.select2');
            } else {
                $('#incidence-worker-filter').val(committedValues.worker_id);
            }

            if ($.fn.select2 && $('#incidence-type-filter').hasClass('select2-hidden-accessible')) {
                $('#incidence-type-filter').val(committedValues.type).trigger('change.select2');
            } else {
                $('#incidence-type-filter').val(committedValues.type);
            }

            if ($.fn.select2 && $('#incidence-status-filter').hasClass('select2-hidden-accessible')) {
                $('#incidence-status-filter').val(committedValues.status).trigger('change.select2');
            } else {
                $('#incidence-status-filter').val(committedValues.status);
            }
        }

        // On modal hide/cancel without submit, restore draft edits to committed state
        $modal.on('hidden.bs.modal', function () {
            restoreCommittedValues();
        });

        // "Limpiar filtros" button: resets modal inputs without submitting
        var $resetBtn = $('#incidences-reset-filters-btn');
        if ($resetBtn.length) {
            $resetBtn.on('click', function () {
                var today = new Date();
                var yyyy = today.getFullYear();
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var dd = String(today.getDate()).padStart(2, '0');
                var todayStr = yyyy + '-' + mm + '-' + dd;

                $('#incidence-date-from').val(todayStr);
                $('#incidence-date-to').val(todayStr);

                if ($.fn.select2 && $('#incidence-worker-filter').hasClass('select2-hidden-accessible')) {
                    $('#incidence-worker-filter').val('').trigger('change.select2');
                } else {
                    $('#incidence-worker-filter').val('');
                }

                if ($.fn.select2 && $('#incidence-type-filter').hasClass('select2-hidden-accessible')) {
                    $('#incidence-type-filter').val('').trigger('change.select2');
                } else {
                    $('#incidence-type-filter').val('');
                }

                if ($.fn.select2 && $('#incidence-status-filter').hasClass('select2-hidden-accessible')) {
                    $('#incidence-status-filter').val('').trigger('change.select2');
                } else {
                    $('#incidence-status-filter').val('');
                }
            });
        }
    }

    function initIncidencesDataTable() {
        if (!$ || !$.fn.DataTable) return;

        var $table = $('#checador-incidences-table');
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
                    targets: 6, // Columna Acciones
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }
            ],
            language: {
                processing: 'Procesando...',
                lengthMenu: 'Mostrar _MENU_ incidencias',
                zeroRecords: 'No se encontraron incidencias que coincidan con la búsqueda.',
                emptyTable: 'No se encontraron incidencias para los filtros seleccionados.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ incidencias',
                infoEmpty: 'Mostrando 0 a 0 de 0 incidencias',
                infoFiltered: '(filtrado de un total de _MAX_ incidencias)',
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

    if (window.jQuery) {
        $(document).ready(function () {
            initIncidencesSelect2();
            initIncidencesFilterModal();
            initIncidencesDataTable();
        });
    }
})(window.jQuery);

document.addEventListener('alpine:init', () => {
    Alpine.data('checadorIncidencePage', () => ({
        formWorkerId: '',
        searchDate: '',
        searchWorkerId: '',
        appliedSearchDate: '',
        appliedSearchWorkerId: '',
        searchSubmitting: false,

        init() {
            this.formWorkerId = String(this.$el.dataset.createWorker || '');
            this.appliedSearchDate = String(this.$el.dataset.appliedDate || '');
            this.appliedSearchWorkerId = String(this.$el.dataset.appliedWorker || '');
            this.prepareIncidenceSearchDraft();
            this.bindPunchCheckFields();
            this.$nextTick(() => {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    this.bindModalSelect2({
                        modalRef: 'checador-incidence-search-modal',
                        selectRef: 'incidenceSearchWorker',
                        wrapperRef: 'incidenceSearchWorkerWrapper',
                        model: 'searchWorkerId',
                        options: {
                            dropdownParent: window.jQuery('#checador-incidence-search-modal'),
                            width: '100%',
                            placeholder: 'Todos los colaboradores',
                            minimumResultsForSearch: 0
                        },
                        namespace: 'checadorIncidenceSearch'
                    });

                    this.bindModalSelect2({
                        modalRef: 'checador-create-incidence-modal',
                        selectRef: 'incidenceWorkerSelect',
                        wrapperRef: 'incidenceWorkerSelectWrapper',
                        model: 'formWorkerId',
                        options: {
                            dropdownParent: window.jQuery('#checador-create-incidence-modal'),
                            width: '100%',
                            placeholder: 'Selecciona un colaborador'
                        },
                        namespace: 'checadorIncidenceCreate'
                    });
                }

                const searchModal = this.$refs.incidenceSearchModal;
                if (searchModal) {
                    searchModal.addEventListener('show.bs.modal', () => this.prepareIncidenceSearchDraft());
                    searchModal.addEventListener('hidden.bs.modal', () => this.discardIncidenceSearchDraft());
                }

                if (this.$el.dataset.openCreate === '1' && window.bootstrap) {
                    const modal = document.getElementById('checador-create-incidence-modal');
                    if (modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        },

        prepareIncidenceSearchDraft() {
            this.searchDate = this.appliedSearchDate;
            this.searchWorkerId = this.appliedSearchWorkerId;
            this.searchSubmitting = false;

            if (this.$refs.incidenceSearchDate) {
                this.$refs.incidenceSearchDate.value = this.searchDate;
            }
            if (this.$refs.incidenceSearchWorker) {
                this.$refs.incidenceSearchWorker.value = this.searchWorkerId;
            }
        },

        discardIncidenceSearchDraft() {
            this.searchDate = this.appliedSearchDate;
            this.searchWorkerId = this.appliedSearchWorkerId;
            this.searchSubmitting = false;

            this.$nextTick(() => {
                if (this.$refs.incidenceSearchDate) {
                    this.$refs.incidenceSearchDate.value = this.appliedSearchDate;
                }
                if (this.$refs.incidenceSearchWorker) {
                    const select = this.$refs.incidenceSearchWorker;
                    select.value = this.appliedSearchWorkerId;
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2
                        && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                        window.jQuery(select).val(this.appliedSearchWorkerId).trigger('change.select2');
                    }
                }
            });
        },

        bindPunchCheckFields() {
            const form = document.getElementById('checador-create-incidence-form');
            if (!form) return;
            const category = document.getElementById('incidence-create-category');
            const period = document.getElementById('incidence-create-period-fields');
            const punch = document.getElementById('incidence-create-punch-check-fields');
            const checkDate = document.getElementById('incidence-create-check-date');
            const checkKind = document.getElementById('incidence-create-check-kind');
            const nature = document.getElementById('incidence-create-nature');
            const source = document.getElementById('incidence-create-source-event-fields');
            const sourceControls = source ? Array.from(source.querySelectorAll('input')) : [];
            const startDate = document.getElementById('incidence-create-start');
            const endDate = document.getElementById('incidence-create-end');
            if (!category || !period || !punch || !checkDate || !checkKind || !nature || !startDate || !endDate) return;

            const sync = () => {
                const isPunch = category.value === 'PUNCH_ATTENDANCE';
                period.hidden = isPunch;
                punch.hidden = !isPunch;
                startDate.required = !isPunch;
                endDate.required = !isPunch;
                [checkDate, checkKind, nature].forEach(control => {
                    control.disabled = !isPunch;
                    control.required = isPunch;
                });
                if (isPunch && checkDate.value) {
                    startDate.value = checkDate.value;
                    endDate.value = checkDate.value;
                }

                const needsSourceEvent = isPunch && nature.value === 'LATENESS';
                if (source) source.hidden = !needsSourceEvent;
                sourceControls.forEach(control => {
                    control.disabled = !needsSourceEvent;
                    control.required = needsSourceEvent;
                });
            };
            category.addEventListener('change', sync);
            checkDate.addEventListener('input', () => {
                if (category.value === 'PUNCH_ATTENDANCE') {
                    startDate.value = checkDate.value;
                    endDate.value = checkDate.value;
                }
            });
            checkDate.addEventListener('change', () => {
                if (category.value === 'PUNCH_ATTENDANCE') {
                    startDate.value = checkDate.value;
                    endDate.value = checkDate.value;
                }
            });
            nature.addEventListener('change', sync);
            form.addEventListener('submit', () => {
                if (category.value === 'PUNCH_ATTENDANCE' && checkDate.value) {
                    startDate.value = checkDate.value;
                    endDate.value = checkDate.value;
                }
            });
            sync();
        }
    }));
});
