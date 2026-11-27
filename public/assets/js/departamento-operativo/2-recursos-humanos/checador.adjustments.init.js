(function (window, document) {
    'use strict';

    function initializeTable() {
        var table = document.querySelector('[data-checador-adjustments]');
        if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable
            && !window.jQuery.fn.DataTable.isDataTable(table)) {
            window.jQuery(table).DataTable({
                pageLength: 25,
                lengthMenu: [10, 25, 50],
                order: [[1, 'desc']],
                columnDefs: [{ targets: [5], orderable: false, searchable: false }],
                language: {
                    emptyTable: 'No hay ajustes en este resultado.',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ ajustes',
                    infoEmpty: 'Sin ajustes',
                    infoFiltered: '(filtrado de _MAX_ ajustes)',
                    lengthMenu: 'Mostrar _MENU_ ajustes',
                    loadingRecords: 'Cargando…',
                    processing: 'Procesando…',
                    search: 'Buscar en esta página:',
                    zeroRecords: 'No se encontraron ajustes',
                    paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                    aria: { sortAscending: ': ordenar ascendente', sortDescending: ': ordenar descendente' }
                }
            });
        }
    }

    function bindWorkerSelect(modal) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2 || !modal || modal.dataset.m07Select2Bound === '1') return;
        modal.dataset.m07Select2Bound = '1';
        function initialize() {
            modal.querySelectorAll('[data-m07-worker-select]').forEach(function (select) {
                var $select = $(select);
                if (!$select.hasClass('select2-hidden-accessible')) {
                    $select.select2({ width: '100%', language: 'es', minimumResultsForSearch: 0, dropdownParent: $(modal), placeholder: 'Todos', allowClear: true });
                }
                $select.css('width', '100%');
                $select.next('.select2-container').css('width', '100%');
            });
        }
        function destroy() {
            modal.querySelectorAll('[data-m07-worker-select]').forEach(function (select) {
                var $select = $(select);
                if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
            });
        }
        modal.addEventListener('shown.bs.modal', initialize);
        modal.addEventListener('hidden.bs.modal', destroy);
        if (modal.classList.contains('show')) initialize();
    }

    function bindDetailTabs() {
        document.querySelectorAll('[data-adjustment-tab]').forEach(function (action) {
            action.addEventListener('click', function () {
                var selector = action.getAttribute('data-bs-target');
                var targetTab = action.getAttribute('data-adjustment-tab');
                var modal = selector ? document.querySelector(selector) : null;
                if (!modal) return;
                modal.addEventListener('shown.bs.modal', function () {
                    var tab = modal.querySelector('[id$="-' + (targetTab === 'history' ? 'history-tab' : 'detail-tab') + '"]');
                    if (tab && window.bootstrap && window.bootstrap.Tab) window.bootstrap.Tab.getOrCreateInstance(tab).show();
                }, { once: true });
            });
        });
    }

    function bindStationNavigation() {
        var path = '/departamento-operativo/recursos-humanos/checador/ajustes';
        var currentPath = window.location.pathname.replace(/\/$/, '');
        if (currentPath !== path) return;
        var selectorApi = typeof ModuleStationSelector !== 'undefined' ? ModuleStationSelector : window.ModuleStationSelector;
        if (!selectorApi) return;
        selectorApi.init('checador', {
            customReload: function (instance) {
                var value = instance.selector ? instance.selector.value : '';
                var match = /^(?:estacion|depto)_([1-9][0-9]*)$/.exec(value || '');
                var url = new URL(path, window.location.origin);
                if (match) url.searchParams.set('workplace_id', match[1]);
                window.location.assign(url.toString());
            }
        });
    }

    function validateDateRange() {
        var form = document.querySelector('[data-m07-adjustment-filter-form]');
        if (!form) return;
        form.addEventListener('submit', function (event) {
            var start = form.querySelector('[name="start_date"]');
            var end = form.querySelector('[name="end_date"]');
            if (!start || !end || !start.value || !end.value) return;
            var startTime = Date.parse(start.value + 'T00:00:00Z');
            var endTime = Date.parse(end.value + 'T00:00:00Z');
            var days = Math.floor((endTime - startTime) / 86400000) + 1;
            if (!Number.isFinite(days) || days < 1 || days > 31) {
                event.preventDefault();
                end.setCustomValidity('El rango debe contener de 1 a 31 fechas laborales.');
                end.reportValidity();
                end.addEventListener('input', function clearRangeError() {
                    end.setCustomValidity('');
                    end.removeEventListener('input', clearRangeError);
                });
            }
        });
    }

    function initialize() {
        initializeTable();
        document.querySelectorAll('[data-m07-adjustment-search-modal]').forEach(bindWorkerSelect);
        bindDetailTabs();
        bindStationNavigation();
        validateDateRange();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})(window, document);
