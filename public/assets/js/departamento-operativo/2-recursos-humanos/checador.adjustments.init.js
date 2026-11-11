(function ($) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
        });
    }

    function showFeedback(message, kind) {
        [document.getElementById('adjustments-read-feedback'), document.getElementById('adjustments-search-feedback')].forEach(function (element) {
            if (!element) return;
            element.className = 'alert alert-' + (kind || 'warning');
            element.textContent = message;
        });
    }

    function clearFeedback() {
        [document.getElementById('adjustments-read-feedback'), document.getElementById('adjustments-search-feedback')].forEach(function (element) {
            if (!element) return;
            element.className = 'alert d-none';
            element.textContent = '';
        });
    }

    function enableFilterControls() {
        var form = document.getElementById('checador-adjustments-filters');
        if (!form) return;
        Array.prototype.forEach.call(form.elements, function (control) {
            control.disabled = false;
        });
    }

    function showAjaxFailure(xhr, textStatus) {
        var status = Number(xhr && xhr.status) || 0;
        var body = xhr && xhr.responseJSON;
        if (!body && xhr && typeof xhr.responseText === 'string') {
            try { body = JSON.parse(xhr.responseText); } catch (_error) { body = null; }
        }
        var message = body && typeof body.error === 'string' ? body.error : '';
        if (!message) {
            if (status === 401) message = 'Inicia sesión nuevamente.';
            else if (status === 403) message = 'No tienes acceso a esta consulta.';
            else if (status === 404) message = 'La consulta de ajustes no está disponible.';
            else if (status >= 500) message = 'El servicio de ajustes encontró un problema. Inténtalo más tarde.';
            else if (status === 0 && /timeout/i.test(String(textStatus || (xhr && xhr.statusText) || ''))) message = 'La consulta tardó demasiado. Inténtalo nuevamente.';
            else if (status === 0) message = 'No fue posible conectar con la consulta de ajustes.';
            else message = 'No fue posible consultar los ajustes. Inténtalo nuevamente.';
        }
        showFeedback(message, 'warning');
    }

    function emptyServerResponse(draw) {
        return {
            draw: Number.isInteger(Number(draw)) && Number(draw) >= 0 ? Number(draw) : 0,
            recordsTotal: 0,
            recordsFiltered: 0,
            data: []
        };
    }

    function setReadError(hasError) {
        var table = document.getElementById('checador-adjustments-table');
        if (!table) return;
        if (hasError) table.dataset.readError = '1';
        else delete table.dataset.readError;
    }

    function filtersValid() {
        var from = document.getElementById('adjustments-date-from').value;
        var to = document.getElementById('adjustments-date-to').value;
        if (!from || !to || to < from) {
            showFeedback('Indica un periodo válido.', 'warning');
            return false;
        }
        var start = new Date(from + 'T00:00:00Z');
        var end = new Date(to + 'T00:00:00Z');
        var days = Math.floor((end.getTime() - start.getTime()) / 86400000);
        if (days > 30) {
            showFeedback('El periodo máximo de consulta es de 31 días.', 'warning');
            return false;
        }
        return true;
    }

    function setTextField(container, label, value) {
        var term = document.createElement('dt');
        term.className = 'col-sm-4';
        term.textContent = label;
        var description = document.createElement('dd');
        description.className = 'col-sm-8 text-break';
        description.textContent = value == null || value === '' ? 'No disponible' : String(value);
        container.append(term, description);
    }

    function openDetail(uuid) {
        var root = document.getElementById('checador-adjustments-read');
        var modalElement = document.getElementById('checador-adjustment-detail-modal');
        if (!root || !modalElement || !window.bootstrap) return;
        var feedback = document.getElementById('adjustment-detail-feedback');
        var fields = document.getElementById('adjustment-detail-fields');
        var historyBody = document.getElementById('adjustment-detail-history');
        var related = document.getElementById('adjustment-linked-evaluations');
        fields.replaceChildren();
        historyBody.replaceChildren();
        related.textContent = 'Consultando…';
        feedback.className = 'alert d-none';
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        var url = root.dataset.detailUrl + '/' + encodeURIComponent(uuid) + '?workplace_id=' + encodeURIComponent(root.dataset.workplaceId);
        fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) throw new Error(body.error || 'No fue posible consultar el detalle.');
                    return body.data;
                });
            })
            .then(function (data) {
                var item = data.adjustment;
                setTextField(fields, 'Referencia', item.uuid_ajuste);
                setTextField(fields, 'Referencia lógica', item.uuid_ajuste_logico);
                setTextField(fields, 'Tipo', item.adjustment_type);
                setTextField(fields, 'Estado', item.status);
                setTextField(fields, 'Revisión', item.revision_number);
                setTextField(fields, 'Colaborador', item.worker_label || ('Personal #' + item.worker_id));
                setTextField(fields, 'Localidad', item.workplace_label || ('Localidad #' + item.workplace_id));
                setTextField(fields, 'Fecha afectada', item.work_date_local);
                setTextField(fields, 'Zona horaria', item.workplace_timezone);
                setTextField(fields, 'Motivo', item.motive);
                setTextField(fields, 'Entrada interpretada (UTC)', item.interpreted_entry_at_utc);
                setTextField(fields, 'Salida interpretada (UTC)', item.interpreted_exit_at_utc);
                setTextField(fields, 'Creado (UTC)', item.created_at_utc);
                setTextField(fields, 'Creado por', 'Usuario #' + item.created_by_user_id);
                setTextField(fields, 'Aprobado por', 'Usuario #' + item.approved_by_user_id);
                setTextField(fields, 'Revisión anterior', item.previous_revision_uuid);
                data.revision_history.forEach(function (revision) {
                    var row = document.createElement('tr');
                    [revision.revision_number, revision.status, revision.created_at_utc, 'Usuario #' + revision.created_by_user_id].forEach(function (value) {
                        var cell = document.createElement('td');
                        cell.textContent = String(value == null ? '' : value);
                        row.appendChild(cell);
                    });
                    historyBody.appendChild(row);
                });
                if (!data.linked_evaluations.length) {
                    related.textContent = 'No se encontraron evaluaciones relacionadas.';
                } else {
                    var list = document.createElement('ul');
                    list.className = 'mb-0';
                    data.linked_evaluations.forEach(function (evaluation) {
                        var entry = document.createElement('li');
                        entry.textContent = evaluation.work_date_local + ' · revisión ' + evaluation.revision_number + ' · ' + evaluation.status + ' · ' + evaluation.created_at_utc;
                        list.appendChild(entry);
                    });
                    related.replaceChildren(list);
                }
            })
            .catch(function (error) {
                feedback.className = 'alert alert-warning';
                feedback.textContent = error.message || 'No fue posible consultar el detalle.';
                related.textContent = '';
            });
    }

    function init() {
        var root = document.getElementById('checador-adjustments-read');
        var table = document.getElementById('checador-adjustments-table');
        if (!root || !table || !$ || !$.fn || !$.fn.DataTable) return;
        if ($.fn.DataTable.isDataTable(table)) return;
        var searchModal = document.getElementById('checador-adjustment-search-modal');
        var $searchModal = searchModal ? $(searchModal) : $();
        var $worker = $('#adjustments-worker');
        if ($worker.length && $.fn.select2 && !$worker.hasClass('select2-hidden-accessible')) {
            $worker.select2({ width: '100%', placeholder: 'Todos los colaboradores', allowClear: true, minimumResultsForSearch: 0, dropdownParent: $searchModal, dropdownCssClass: 'adjustments-filter-dropdown' });
        }
        ['#adjustments-type', '#adjustments-status'].forEach(function (selector) {
            var $select = $(selector);
            if ($select.length && $.fn.select2 && !$select.hasClass('select2-hidden-accessible')) {
                $select.select2({ width: '100%', minimumResultsForSearch: 10, dropdownParent: $searchModal, dropdownCssClass: 'adjustments-filter-dropdown' });
            }
        });

        function readFilters() {
            return {
                worker: $worker.val() || '',
                type: $('#adjustments-type').val() || '',
                status: $('#adjustments-status').val() || '',
                from: document.getElementById('adjustments-date-from').value,
                to: document.getElementById('adjustments-date-to').value
            };
        }

        function writeFilters(filters) {
            $worker.val(filters.worker).trigger('change');
            $('#adjustments-type').val(filters.type).trigger('change');
            $('#adjustments-status').val(filters.status).trigger('change');
            document.getElementById('adjustments-date-from').value = filters.from;
            document.getElementById('adjustments-date-to').value = filters.to;
        }

        function refreshWorkerOptions(options, status) {
            if (!$worker.length || !Array.isArray(options)) return;
            var select = $worker.get(0);
            var selected = String($worker.val() || '');
            var selectedLabel = $worker.find('option:selected').text();
            var normalized = options.filter(function (option) {
                return option && Number.isInteger(Number(option.worker_id))
                    && Number(option.worker_id) > 0 && typeof option.full_name === 'string' && option.full_name.trim() !== '';
            });
            var authorizedIds = normalized.map(function (option) { return String(Number(option.worker_id)); });
            var newOptions = [new Option('Todos los colaboradores', '')];
            normalized.forEach(function (option) {
                newOptions.push(new Option(option.full_name.trim(), String(Number(option.worker_id))));
            });
            // Keep a selected worker after a successful, server-authorized
            // period query even when their membership ends before the period's
            // final day; the API already validated that requested period.
            if (selected && !authorizedIds.includes(selected) && selectedLabel) {
                newOptions.push(new Option(selectedLabel, selected));
            }
            select.replaceChildren.apply(select, newOptions);
            var selectedStillExists = Array.prototype.some.call(select.options, function (option) { return option.value === selected; });
            $worker.val(selected && selectedStillExists ? selected : '').trigger('change');

            var statusLabel = document.getElementById('adjustments-roster-feedback');
            if (statusLabel) {
                statusLabel.textContent = status === 'ready'
                    ? 'Padrón autorizado validado para el último día del periodo.'
                    : (['partial', 'current_roster_only'].indexOf(status) !== -1
                        ? 'La lista puede estar incompleta para el último día del periodo.'
                        : 'No se confirmó el padrón autorizado para este periodo.');
            }
            root.dataset.rosterStatus = String(status || 'unavailable');
        }

        var defaultDate = root.dataset.defaultDate || '';
        var appliedFilters = readFilters();
        var applyingFromModal = false;
        if (searchModal) {
            searchModal.addEventListener('show.bs.modal', function () {
                writeFilters(appliedFilters);
                clearFeedback();
            });
            searchModal.addEventListener('hidden.bs.modal', function () {
                writeFilters(appliedFilters);
                applyingFromModal = false;
            });
        }
        var resetButton = document.getElementById('adjustments-reset-filters');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                writeFilters({ worker: '', type: '', status: '', from: defaultDate, to: defaultDate });
                clearFeedback();
            });
        }

        var dataTable = $(table).DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            ordering: false,
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ajax: function (request, callback) {
                var params = $.extend({}, request, {
                    workplace_id: root.dataset.workplaceId,
                    date_from: document.getElementById('adjustments-date-from').value,
                    date_to: document.getElementById('adjustments-date-to').value,
                    worker_id: $worker.val() || '',
                    adjustment_type: $('#adjustments-type').val() || '',
                    status: $('#adjustments-status').val() || ''
                });
                var xhr;
                try {
                    xhr = $.ajax({
                        url: root.dataset.listUrl,
                        type: 'GET',
                        dataType: 'json',
                        cache: false,
                        data: params
                    });
                } catch (error) {
                    showAjaxFailure(null, error && error.message);
                    setReadError(true);
                    callback(emptyServerResponse(request.draw));
                    enableFilterControls();
                    return null;
                }

                return xhr.done(function (response) {
                    if (!response || !Array.isArray(response.data)
                        || !Number.isInteger(response.recordsTotal)
                        || !Number.isInteger(response.recordsFiltered)) {
                        showFeedback('La respuesta de ajustes no está disponible. Inténtalo nuevamente.', 'warning');
                        setReadError(true);
                        applyingFromModal = false;
                        callback(emptyServerResponse(request.draw));
                        return;
                    }
                    setReadError(false);
                    clearFeedback();
                    if (Array.isArray(response.worker_options)) {
                        refreshWorkerOptions(response.worker_options, String(response.worker_roster_status || 'unavailable'));
                    }
                    callback(response);
                    if (applyingFromModal && searchModal) {
                        appliedFilters = readFilters();
                        applyingFromModal = false;
                        if (window.bootstrap && window.bootstrap.Modal) {
                            window.bootstrap.Modal.getOrCreateInstance(searchModal).hide();
                        }
                    }
                }).fail(function (failedXhr, textStatus) {
                    showAjaxFailure(failedXhr, textStatus);
                    setReadError(true);
                    applyingFromModal = false;
                    // This is a client-side DataTables fallback only. The failed
                    // HTTP status remains unchanged and is never reported as 200.
                    callback(emptyServerResponse(request.draw));
                }).always(enableFilterControls);
            },
            drawCallback: function () {
                var api = this.api();
                var info = api.page.info();
                var wrapper = table.closest('.dataTables_wrapper');
                var isEmpty = !info.recordsDisplay;
                if (wrapper) {
                    ['.dataTables_length', '.dataTables_info', '.dataTables_paginate'].forEach(function (selector) {
                        var control = wrapper.querySelector(selector);
                        if (control) control.classList.toggle('d-none', isEmpty);
                    });
                }
                if (table.dataset.readError === '1') {
                    var emptyCell = table.querySelector('tbody td.dataTables_empty');
                    if (emptyCell) emptyCell.textContent = '';
                }
            },
            columns: [
                { data: 'work_date_local', render: $.fn.dataTable.render.text() },
                { data: 'worker_label', render: $.fn.dataTable.render.text() },
                { data: 'workplace_label', render: $.fn.dataTable.render.text() },
                { data: 'adjustment_type_label', render: $.fn.dataTable.render.text() },
                { data: 'motive', render: $.fn.dataTable.render.text() },
                { data: 'status_label', render: $.fn.dataTable.render.text() },
                { data: 'created_by_label', render: $.fn.dataTable.render.text() },
                {
                    data: 'uuid_ajuste',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (value, type) {
                        if (type !== 'display') return value;
                        return '<button type="button" class="btn btn-sm btn-outline-primary js-adjustment-detail" data-adjustment-uuid="' + escapeHtml(value) + '"><i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle</button>';
                    }
                }
            ],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json',
                processing: 'Consultando ajustes…',
                emptyTable: 'No se encontraron ajustes administrativos para los filtros seleccionados.',
                zeroRecords: 'No se encontraron ajustes administrativos para los filtros seleccionados.'
            }
        });

        document.getElementById('checador-adjustments-filters').addEventListener('submit', function (event) {
            event.preventDefault();
            if (!filtersValid()) return;
            clearFeedback();
            applyingFromModal = true;
            dataTable.page('first').draw('page');
        });
        $(table).on('click', '.js-adjustment-detail', function () {
            openDetail(this.getAttribute('data-adjustment-uuid') || '');
        });
    }

    if (window.jQuery) $(document).ready(init);
})(window.jQuery);
