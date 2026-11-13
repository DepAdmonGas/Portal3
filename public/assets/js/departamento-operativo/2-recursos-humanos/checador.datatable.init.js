document.addEventListener('DOMContentLoaded', function () {
    var periodForm = document.getElementById('punch-report-period-form');
    var periodSelect = document.getElementById('punch-report-period');
    var monthControl = document.querySelector('[data-period-month]');
    var yearControl = document.querySelector('[data-period-year]');
    var filterModal = document.getElementById('punch-report-filter-modal');
    var filterForm = document.getElementById('punch-report-filter-form');
    var workerFilter = document.getElementById('punch-report-worker');

    if (workerFilter && window.jQuery && jQuery.fn && jQuery.fn.select2
        && !jQuery(workerFilter).hasClass('select2-hidden-accessible')) {
        jQuery(workerFilter).select2({
            width: '100%',
            dropdownCssClass: 'punch-report-worker-dropdown',
            placeholder: 'Todos los colaboradores',
            allowClear: true,
            tags: false,
            minimumInputLength: 0,
            language: {
                noResults: function () { return 'No se encontraron colaboradores en esta localidad.'; },
                searching: function () { return 'Buscando colaboradores…'; },
                errorLoading: function () { return 'No se pudo consultar la lista de colaboradores.'; }
            },
            ajax: {
                url: workerFilter.dataset.searchUrl,
                dataType: 'json',
                delay: 250,
                cache: false,
                data: function (params) {
                    return { term: params.term || '', page: params.page || 1 };
                },
                processResults: function (payload) {
                    return {
                        results: Array.isArray(payload && payload.results) ? payload.results : [],
                        pagination: { more: Boolean(payload && payload.pagination && payload.pagination.more) }
                    };
                }
            }
        });
    }

    function syncPeriodControls() {
        var isSpecific = periodSelect && periodSelect.value === 'specific';
        if (monthControl) monthControl.classList.toggle('d-none', !isSpecific);
        if (yearControl) yearControl.classList.toggle('d-none', !isSpecific);
    }

    function openCustomRange() {
        if (filterModal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(filterModal).show();
        }
    }

    function setHiddenValue(formElement, name, value) {
        if (!formElement) return;
        var input = formElement.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            formElement.appendChild(input);
        }
        input.value = value;
    }

    if (periodForm && periodSelect) {
        syncPeriodControls();
        periodSelect.addEventListener('change', syncPeriodControls);
        periodForm.addEventListener('submit', function (event) {
            if (periodSelect.value === 'custom') {
                event.preventDefault();
                openCustomRange();
                return;
            }
            if (periodSelect.value !== 'specific') return;

            event.preventDefault();
            var yearInput = document.getElementById('punch-report-year');
            var monthInput = document.getElementById('punch-report-month');
            var year = Number(yearInput && yearInput.value);
            var month = Number(monthInput && monthInput.value);
            if (!Number.isInteger(year) || year < 2000 || year > new Date().getUTCFullYear()
                || !Number.isInteger(month) || month < 1 || month > 12) {
                if (yearInput && typeof yearInput.reportValidity === 'function') yearInput.reportValidity();
                return;
            }
            var firstDate = new Date(Date.UTC(year, month - 1, 1)).toISOString().slice(0, 10);
            var lastDate = new Date(Date.UTC(year, month, 0)).toISOString().slice(0, 10);
            setHiddenValue(periodForm, 'from_date', firstDate);
            setHiddenValue(periodForm, 'to_date', lastDate);
            periodSelect.value = 'year';
            periodForm.submit();
        });
    }

    if (filterModal && filterForm) {
        filterModal.addEventListener('show.bs.modal', function () {
            var workerInput = filterForm.querySelector('input[name="worker_id"]');
            if (workerInput && workerFilter) workerInput.value = workerFilter.value;
        });
        var clearFiltersButton = filterForm.querySelector('[data-checador-clear-filters]');
        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', function () {
                ['from_date', 'to_date', 'worker_id', 'capture_method', 'processing_status'].forEach(function (name) {
                    var input = filterForm.querySelector('[name="' + name + '"]');
                    if (input) input.value = '';
                });
                if (workerFilter) jQuery(workerFilter).val('').trigger('change');
                var searchInput = document.getElementById('dt_search');
                if (searchInput) searchInput.value = '';
                setHiddenValue(filterForm, 'period', 'today');
                filterForm.submit();
            });
        }
        filterForm.addEventListener('submit', function (event) {
            var workerInput = filterForm.querySelector('input[name="worker_id"]');
            if (workerInput && workerFilter) workerInput.value = workerFilter.value;
            setHiddenValue(filterForm, 'period', 'custom');
        });
    }

    var tableElement = document.getElementById('checador-punch-table');
    if (!tableElement) return;
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
        var libraryError = document.getElementById('punch-report-ajax-error');
        var tableWrapper = document.getElementById('punch-report-table-wrap');
        if (libraryError) {
            libraryError.textContent = 'La tabla no pudo inicializarse. Actualiza la página e inténtalo nuevamente.';
            libraryError.classList.remove('d-none');
        }
        if (tableWrapper) tableWrapper.classList.add('d-none');
        return;
    }

    var $tableElement = jQuery(tableElement);
    var form = document.getElementById('punch-report-filter-form');
    var errorElement = document.getElementById('punch-report-ajax-error');
    var tableWrap = document.getElementById('punch-report-table-wrap');
    var emptyState = document.getElementById('punch-report-empty-state');
    var searchField = document.getElementById('dt_search');
    var orderColumnField = document.getElementById('dt_order_column');
    var orderDirectionField = document.getElementById('dt_order_direction');
    var allowedOrderColumns = [1, 2, 3, 4, 5, 6];

    function findTableSearchInput(dataTable) {
        var container = dataTable.table().container();
        var scope = container && typeof container.querySelector === 'function'
            ? container
            : (tableWrap || document);
        return scope.querySelector('.dataTables_filter input, .dt-search input');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[character];
        });
    }

    // Format stored UTC fields without Date parsing or browser-zone conversion.
    function utcTimestampParts(value) {
        var match = String(value == null ? '' : value).match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})(?::\d{2})?(?: UTC)?$/);
        if (!match) return null;
        var hour = Number(match[2]);
        var minute = Number(match[3]);
        if (hour > 23 || minute > 59) return null;
        return { date: match[1], hour: hour, minute: match[3] };
    }

    function formatUtc12Hour(value) {
        var parts = utcTimestampParts(value);
        if (!parts) return value == null || value === '' ? 'No disponible' : String(value);
        var suffix = parts.hour < 12 ? 'am' : 'pm';
        var hour12 = parts.hour % 12 || 12;
        return parts.date + ' · ' + String(hour12).padStart(2, '0') + ':' + parts.minute + ' ' + suffix + ' UTC';
    }

    function formatUtcTime12Hour(value) {
        var parts = utcTimestampParts(value);
        if (!parts) return 'No disponible';
        var suffix = parts.hour < 12 ? 'am' : 'pm';
        var hour12 = parts.hour % 12 || 12;
        return String(hour12).padStart(2, '0') + ':' + parts.minute + ' ' + suffix;
    }

    function formatUtcDate(value) {
        var parts = utcTimestampParts(value);
        return parts ? parts.date : 'No disponible';
    }

    function showRequestError() {
        if (errorElement) errorElement.classList.remove('d-none');
        if (tableWrap) tableWrap.classList.add('d-none');
        if (emptyState) emptyState.classList.add('d-none');
    }

    function showRequestSuccess() {
        if (errorElement) errorElement.classList.add('d-none');
        if (tableWrap) tableWrap.classList.remove('d-none');
    }

    function updateEmptyState(dataTable) {
        if (!emptyState || !tableWrap) return;
        var response = dataTable.ajax.json();
        var hasNoRows = response && Number(response.recordsTotal) === 0;
        emptyState.classList.toggle('d-none', !hasNoRows);
        tableWrap.classList.toggle('d-none', hasNoRows);
        if (!hasNoRows) return;
        var title = emptyState.querySelector('[data-empty-title]');
        var description = emptyState.querySelector('[data-empty-description]');
        if (title) title.textContent = 'Sin marcaciones en el periodo consultado';
        if (description) description.textContent = 'Prueba con otro periodo o ajusta los filtros.';
    }

    function updateCsvState(dataTable) {
        if (!form) return;
        var searchInput = findTableSearchInput(dataTable);
        if (searchField) {
            searchField.value = searchInput ? searchInput.value : dataTable.search();
        }
        var order = dataTable.order();
        if (order && order.length && allowedOrderColumns.indexOf(Number(order[0][0])) !== -1) {
            if (orderColumnField) orderColumnField.value = String(order[0][0]);
            if (orderDirectionField) orderDirectionField.value = order[0][1] === 'desc' ? 'desc' : 'asc';
        }
    }

    function setDetailText(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = value == null || value === '' ? 'No disponible' : String(value);
    }

    function showDetail(row) {
        setDetailText('punch-detail-event-uuid', row.event_uuid);
        setDetailText('punch-detail-station', row.station);
        setDetailText('punch-detail-collaborator', row.collaborator);
        setDetailText('punch-detail-device', row.device);
        setDetailText('punch-detail-method', row.capture_method);
        setDetailText('punch-detail-source-time', row.source_timestamp_raw);
        setDetailText('punch-detail-source-zone', row.source_timezone_reported);
        setDetailText('punch-detail-occurred', row.ocurrido_at_utc
            ? formatUtc12Hour(row.ocurrido_at_utc)
            : 'Hora de marcación no determinada. La tabla muestra la fecha y hora de recepción.');
        setDetailText('punch-detail-received', row.recibido_at_utc_detail
            ? formatUtc12Hour(row.recibido_at_utc_detail)
            : 'No disponible');
        setDetailText('punch-detail-integrity', row.integrity || 'Integridad no verificada por Portal3');

        var statusElement = document.getElementById('punch-detail-status');
        if (statusElement) {
            statusElement.textContent = '';
            var badge = document.createElement('span');
            var knownBadgeClasses = ['text-bg-info', 'text-bg-warning', 'text-bg-danger', 'text-bg-secondary'];
            badge.className = 'badge ' + (knownBadgeClasses.indexOf(row.processing_status_class) !== -1
                ? row.processing_status_class
                : 'text-bg-secondary');
            badge.textContent = row.processing_status || 'Estado no disponible';
            statusElement.appendChild(badge);
            if (row.processing_explanation) {
                var explanation = document.createElement('span');
                explanation.className = 'd-block small text-muted mt-1';
                explanation.textContent = row.processing_explanation;
                statusElement.appendChild(explanation);
            }
        }

        var modalElement = document.getElementById('checador-punch-detail-modal');
        if (modalElement && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }

    if (jQuery.fn.DataTable.isDataTable(tableElement)) {
        $tableElement.DataTable().destroy();
    }
    $tableElement.find('tbody').empty();

    // This page has one table; suppress DataTables' generic alert so the page
    // can present a Spanish, non-empty-state error instead.
    jQuery.fn.dataTable.ext.errMode = 'none';

    var initialColumn = Number(orderColumnField ? orderColumnField.value : 1);
    if (allowedOrderColumns.indexOf(initialColumn) === -1) initialColumn = 1;
    var initialDirection = orderDirectionField && orderDirectionField.value === 'desc' ? 'desc' : 'asc';

    var dataTable = $tableElement.DataTable({
        processing: false,
        serverSide: true,
        deferRender: true,
        autoWidth: false,
        stateSave: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[initialColumn, initialDirection]],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: '',
            zeroRecords: 'No hay registros que coincidan con la búsqueda actual.'
        },
        ajax: {
            url: tableElement.dataset.endpoint,
            type: 'GET',
            data: function (request) {
                request.from_date = document.getElementById('from_date').value;
                request.to_date = document.getElementById('to_date').value;
                request.worker_id = workerFilter ? workerFilter.value : '';
                request.capture_method = document.getElementById('capture_method').value;
                request.processing_status = document.getElementById('processing_status').value;
            },
            dataSrc: function (json) {
                if (!json || !Array.isArray(json.data)
                    || !Number.isInteger(json.draw)
                    || !Number.isInteger(json.recordsTotal)
                    || !Number.isInteger(json.recordsFiltered)) {
                    showRequestError();
                    return [];
                }
                showRequestSuccess();
                return json.data;
            },
            error: function () {
                showRequestError();
            }
        },
        columnDefs: [
            { orderable: false, searchable: false, targets: [0, 7] }
        ],
        columns: [
            {
                data: 'station',
                title: 'Estación',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: 'event_time_display',
                title: 'Fecha (UTC)',
                className: 'align-middle',
                render: function (value, type) {
                    if (type !== 'display') return value;
                    return escapeHtml(formatUtcDate(value));
                }
            },
            {
                data: 'event_time_display',
                title: 'Hora (UTC)',
                className: 'align-middle',
                render: function (value, type, row) {
                    if (type !== 'display') return value;
                    var html = '<span>' + escapeHtml(formatUtcTime12Hour(value)) + '</span>';
                    if (!row.occurrence_available) html += '<span class="d-block small text-muted">Hora no determinada; se muestra la fecha y hora de recepción.</span>';
                    return html;
                }
            },
            {
                data: 'collaborator',
                title: 'Colaborador',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: 'capture_method',
                title: 'Método de captura',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: 'device',
                title: 'Origen',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: null,
                title: 'Estado de la marcación',
                className: 'align-middle',
                render: function (value, type, row) {
                    if (type !== 'display') return row.processing_status;
                    var classes = ['text-bg-info', 'text-bg-warning', 'text-bg-danger', 'text-bg-secondary'];
                    var badgeClass = classes.indexOf(row.processing_status_class) !== -1
                        ? row.processing_status_class
                        : 'text-bg-secondary';
                    return '<span class="badge ' + badgeClass + '">' + escapeHtml(row.processing_status) + '</span>';
                }
            },
            {
                data: null,
                title: 'Acciones',
                orderable: false,
                searchable: false,
                className: 'table-actions-cell',
                render: function (value, type) {
                    if (type !== 'display') return '';
                    return '<button type="button" class="btn btn-sm btn-outline-primary" data-checador-detail>' +
                        '<i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle</button>';
                }
            }
        ]
    });

    $tableElement.on('error.dt', function () {
        showRequestError();
        return false;
    });
    $tableElement.on('xhr.dt', function (event, settings, json) {
        if (json && Array.isArray(json.data)) showRequestSuccess();
        else showRequestError();
    });
    $tableElement.on('draw.dt', function () {
        updateEmptyState(dataTable);
    });
    $tableElement.on('order.dt', function () {
        updateCsvState(dataTable);
    });
    $tableElement.on('click', '[data-checador-detail]', function () {
        var row = dataTable.row(jQuery(this).closest('tr')).data();
        if (row) showDetail(row);
    });

    if (form) {
        // The report wrapper is part of this view and remains stable even if a
        // DataTables integration returns no API container for its generated UI.
        var searchEventRoot = tableWrap || dataTable.table().container() || document;
        searchEventRoot.addEventListener('input', function (event) {
            if (event.target && event.target.matches('.dataTables_filter input, .dt-search input')) {
                if (searchField) searchField.value = event.target.value;
            }
        });
        form.addEventListener('submit', function () {
            updateCsvState(dataTable);
        });
    }
});
