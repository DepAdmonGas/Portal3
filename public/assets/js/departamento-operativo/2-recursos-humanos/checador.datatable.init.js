document.addEventListener('DOMContentLoaded', function () {
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
    var searchField = document.getElementById('dt_search');
    var orderColumnField = document.getElementById('dt_order_column');
    var orderDirectionField = document.getElementById('dt_order_direction');
    var allowedOrderColumns = [1, 2, 3, 4];

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

    function showRequestError() {
        if (errorElement) errorElement.classList.remove('d-none');
        if (tableWrap) tableWrap.classList.add('d-none');
    }

    function showRequestSuccess() {
        if (errorElement) errorElement.classList.add('d-none');
        if (tableWrap) tableWrap.classList.remove('d-none');
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
            ? row.ocurrido_at_utc + ' UTC'
            : 'Hora de marcación no determinada. La tabla muestra la fecha y hora de recepción.');
        setDetailText('punch-detail-received', row.recibido_at_utc_detail
            ? row.recibido_at_utc_detail + ' UTC'
            : 'No disponible');
        setDetailText('punch-detail-integrity', row.integrity || 'Integridad no verificada por Portal3');

        var statusElement = document.getElementById('punch-detail-status');
        if (statusElement) {
            statusElement.textContent = '';
            var badge = document.createElement('span');
            var knownBadgeClasses = ['text-bg-info', 'text-bg-warning', 'text-bg-secondary'];
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
            emptyTable: 'No se devolvieron marcaciones observadas para este periodo consultado.',
            zeroRecords: 'No hay registros que coincidan con la búsqueda actual.'
        },
        ajax: {
            url: tableElement.dataset.endpoint,
            type: 'GET',
            data: function (request) {
                request.workplace_id = document.getElementById('workplace_id').value;
                request.from_date = document.getElementById('from_date').value;
                request.to_date = document.getElementById('to_date').value;
                request.worker_id = document.getElementById('worker_id').value;
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
            { orderable: false, searchable: false, targets: [0, 5] }
        ],
        columns: [
            {
                data: 'station',
                title: 'Estación',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: 'received_at_utc',
                title: 'Fecha y hora de recepción (UTC)',
                className: 'align-middle',
                render: function (value, type, row) {
                    if (type !== 'display') return value;
                    var html = '<span>' + escapeHtml(value || 'No disponible') + ' <span class="text-muted">UTC</span></span>';
                    if (!row.occurrence_available) {
                        html += '<span class="d-block small text-muted">La hora mostrada es la de recepción.</span>';
                    }
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
                title: 'Tipo de registro',
                className: 'align-middle',
                render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; }
            },
            {
                data: null,
                title: 'Estado de la marcación',
                className: 'align-middle',
                render: function (value, type, row) {
                    if (type !== 'display') return row.processing_status;
                    var classes = ['text-bg-info', 'text-bg-warning', 'text-bg-secondary'];
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
                className: 'text-center align-middle',
                width: '20px',
                render: function (value, type) {
                    if (type !== 'display') return '';
                    return '<button type="button" class="btn btn-sm bg-primary-subtle text-primary" data-checador-detail aria-label="Ver detalle de marcación">' +
                        '<i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle</button>';
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
