document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-checador-filter-form][data-checador-ajax]');
    if (form) {
        var modal = form.closest('[data-checador-filter-modal]');
        var root = document.getElementById('container');
        var dateInput = form.querySelector('[name="date"]');
        var workerInput = form.querySelector('[name="worker_id"]');
        var errorElement = form.querySelector('[data-checador-search-error]');
        var appliedDate = dateInput ? dateInput.value : '';
        var appliedWorker = workerInput ? workerInput.value : '';
        var busy = false;
        var workerSelectWrapper = form.querySelector('#checador-worker-select-wrapper');

        function setWorkerValue(value) {
            if (!workerInput) return;
            workerInput.value = value;
            if (window.jQuery && jQuery.fn && jQuery.fn.select2
                && jQuery(workerInput).hasClass('select2-hidden-accessible')) {
                jQuery(workerInput).trigger('change.select2');
            }
        }

        function restoreDataTableScrollStructure(table) {
            if (!table || !table.closest) return;

            var wrapper = table.closest('.dataTables_wrapper[data-scroll-isolated="true"]');
            var scrollContainer = table.closest('.table-responsive');
            if (!wrapper || !scrollContainer || !wrapper.contains(scrollContainer)) return;

            var scrollContent = table.closest('.dt-row');
            if (!scrollContent || !wrapper.contains(scrollContent)) scrollContent = table;
            if (!scrollContainer.contains(scrollContent) || !wrapper.parentElement) return;

            // datatables-scroll-isolation nests the responsive container inside
            // the wrapper. Restore the original parent chain before DataTables
            // uses its saved reinsertion anchor during destroy().
            wrapper.insertBefore(scrollContent, scrollContainer);
            wrapper.parentElement.insertBefore(scrollContainer, wrapper.nextSibling);
            scrollContainer.appendChild(wrapper);
        }

        function showError(message) {
            if (!errorElement) return;
            errorElement.textContent = message;
            errorElement.classList.remove('d-none');
        }

        function resetDraft() {
            if (dateInput) dateInput.value = appliedDate;
            setWorkerValue(appliedWorker);
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('d-none');
            }
        }

        function requestErrorMessage(status) {
            if (status === 401) return 'La sesión no está disponible. Inicia sesión nuevamente y vuelve a intentar.';
            if (status === 403) return 'No se autorizó la consulta para el centro de trabajo seleccionado.';
            if (status === 419) return 'La sesión venció. Recarga la página e inténtalo de nuevo.';
            if (status === 422) return 'La fecha o el colaborador seleccionado no son válidos.';
            return 'No fue posible consultar la asistencia. Los resultados anteriores permanecen visibles; puedes reintentar.';
        }

        function clearResultsForUnverifiedContext(message) {
            var currentResults = root && root.querySelector('[data-checador-attendance-results]');
            if (currentResults) {
                var notice = document.createElement('div');
                notice.className = 'alert alert-warning py-2';
                notice.setAttribute('role', 'status');
                notice.textContent = message;
                currentResults.replaceWith(notice);
            }
            if (workerInput && workerInput.tagName === 'SELECT') {
                var allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'Todos los colaboradores';
                workerInput.replaceChildren(allOption);
                setWorkerValue('');
                workerInput.disabled = true;
                appliedWorker = '';
            }
        }

        async function search(clearRequested) {
            if (busy || !root) return;
            if (!clearRequested && (!dateInput || !dateInput.value || !dateInput.checkValidity())) {
                if (dateInput && typeof dateInput.reportValidity === 'function') dateInput.reportValidity();
                showError('Selecciona una fecha laboral antes de buscar.');
                return;
            }

            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
            if (!token || !window.axios || typeof window.axios.post !== 'function') {
                showError('La búsqueda no está disponible. Recarga la página e inténtalo de nuevo.');
                return;
            }

            var buttons = Array.from(form.querySelectorAll('[data-checador-search-action]'));
            buttons.forEach(function (button) { button.disabled = true; });
            busy = true;
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('d-none');
            }

            try {
                var response = await window.axios.post(form.action, {
                    date: clearRequested ? null : dateInput.value,
                    worker_id: clearRequested || !workerInput || workerInput.value === '' ? null : workerInput.value
                }, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    },
                    validateStatus: function (status) { return status >= 200 && status < 300; }
                });

                var result = response && response.data;
                if (!result || result.success !== true || typeof result.html !== 'string') {
                    throw new Error('invalid_response');
                }
                var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                var nextRoot = parsed.querySelector('#container');
                var nextResults = nextRoot && nextRoot.querySelector('[data-checador-attendance-results]');
                var nextTable = nextResults && nextResults.querySelector('#checador-daily-attendance-table');
                var currentResults = root.querySelector('[data-checador-attendance-results]');
                var currentTable = currentResults && currentResults.querySelector('#checador-daily-attendance-table');
                var nextDate = nextRoot && nextRoot.querySelector('[data-checador-filter-controls] input[name="date"]');
                var nextWorker = nextRoot && nextRoot.querySelector('[data-checador-filter-controls] [name="worker_id"]');
                var nextContextWorkplace = nextRoot && nextRoot.querySelector('[data-checador-context-workplace]');
                var nextContextDate = nextRoot && nextRoot.querySelector('[data-checador-context-date]');
                if (!nextResults || !currentResults) {
                    throw new Error('invalid_fragment');
                }
                if (nextResults.dataset.workplaceId !== currentResults.dataset.workplaceId) {
                    clearResultsForUnverifiedContext('Cambió el centro autorizado. Recarga la página para confirmar el contexto antes de consultar nuevamente.');
                    throw new Error('attendance_context_changed');
                }
                var nextRosterFallback = nextResults.dataset.attendanceRosterFallback === 'true';
                if ((nextResults.dataset.attendanceState !== 'success' && !nextRosterFallback)
                    || !nextTable || !nextDate || !nextWorker) {
                    throw new Error('invalid_fragment');
                }

                if (currentTable && window.jQuery && jQuery.fn && jQuery.fn.DataTable
                    && jQuery.fn.DataTable.isDataTable(currentTable)) {
                    restoreDataTableScrollStructure(currentTable);
                    jQuery(currentTable).DataTable().destroy();
                }
                currentResults.replaceWith(nextResults);
                var dataTableUpdated = false;
                try {
                    dataTableUpdated = typeof window.initChecadorAttendanceDataTable === 'function'
                        && window.initChecadorAttendanceDataTable(nextResults) === true;
                } catch (_tableInitError) {
                    dataTableUpdated = false;
                }
                if (!dataTableUpdated) {
                    nextResults.replaceWith(currentResults);
                    try {
                        if (currentResults.querySelector('#checador-daily-attendance-table')
                            && typeof window.initChecadorAttendanceDataTable === 'function') {
                            window.initChecadorAttendanceDataTable(currentResults);
                        }
                    } catch (_restoreTableError) {
                        // Keep the previous rows visible even if the widget cannot be restored.
                    }
                    throw new Error('table_update_failed');
                }

                var currentContextWorkplace = root.querySelector('[data-checador-context-workplace]');
                var currentContextDate = root.querySelector('[data-checador-context-date]');
                if (currentContextWorkplace && nextContextWorkplace) {
                    currentContextWorkplace.textContent = nextContextWorkplace.textContent;
                }
                if (currentContextDate && nextContextDate) {
                    currentContextDate.textContent = nextContextDate.textContent;
                }
                appliedDate = nextDate.value;
                appliedWorker = nextWorker.value;
                if (dateInput) dateInput.value = appliedDate;
                if (workerInput) {
                    if (workerInput.tagName === 'SELECT' && nextWorker.options) {
                        workerInput.replaceChildren.apply(workerInput, Array.from(nextWorker.options).map(function (option) {
                            return option.cloneNode(true);
                        }));
                        workerInput.disabled = nextWorker.disabled;
                    }
                    setWorkerValue(appliedWorker);
                }
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    var modalInstance = window.bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                }
            } catch (error) {
                var status = error && error.response ? Number(error.response.status || 0) : 0;
                if (status === 401 || status === 403 || status === 419
                    || (status >= 200 && status < 300 && error && error.message === 'invalid_response')) {
                    clearResultsForUnverifiedContext('La sesión o el alcance autorizado cambió. Recarga la página para consultar asistencia.');
                }
                var message = requestErrorMessage(status);
                if (error && error.message === 'attendance_context_changed') {
                    message = 'Cambió el centro autorizado. Recarga la página para confirmar el contexto.';
                } else if (error && error.message === 'invalid_response' && status >= 200 && status < 300) {
                    message = 'No se pudo confirmar la sesión. Recarga la página antes de volver a consultar.';
                }
                showError(message);
            } finally {
                buttons.forEach(function (button) { button.disabled = false; });
                busy = false;
            }
        }

        if (modal) {
            if (workerInput && window.jQuery && jQuery.fn && jQuery.fn.select2
                && !jQuery(workerInput).hasClass('select2-hidden-accessible')) {
                jQuery(workerInput).select2({
                    dropdownParent: jQuery(modal),
                    width: '100%',
                    placeholder: 'Todos los colaboradores',
                    minimumResultsForSearch: 0
                });
                if (workerSelectWrapper) workerSelectWrapper.classList.remove('is-select2-pending');
            }
            modal.addEventListener('show.bs.modal', resetDraft);
            modal.addEventListener('hidden.bs.modal', resetDraft);
        }
        form.addEventListener('submit', function (event) { event.preventDefault(); });
        form.querySelectorAll('[data-checador-search-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                search(button.getAttribute('data-checador-search-action') === 'clear');
            });
        });
    }

    var punchReportFilterModal = document.getElementById('punch-report-filter-modal');
    var punchReportFilterForm = document.getElementById('punch-report-filter-form');
    if (punchReportFilterModal && punchReportFilterForm) {
        var punchReportAppliedValues = [];
        var punchReportSubmitted = false;
        function capturePunchReportAppliedValues() {
            punchReportAppliedValues = Array.from(punchReportFilterForm.elements)
                .filter(function (control) { return control.name && control.type !== 'button' && control.type !== 'submit'; })
                .map(function (control) { return [control, control.value]; });
        }
        punchReportFilterModal.addEventListener('show.bs.modal', function () {
            var visibleWorker = document.getElementById('punch-report-worker');
            var hiddenWorker = punchReportFilterForm.querySelector('input[name="worker_id"]');
            if (visibleWorker && hiddenWorker) hiddenWorker.value = visibleWorker.value;
            punchReportSubmitted = false;
            capturePunchReportAppliedValues();
        });
        punchReportFilterForm.addEventListener('submit', function () { punchReportSubmitted = true; });
        punchReportFilterModal.addEventListener('hidden.bs.modal', function () {
            if (punchReportSubmitted) return;
            punchReportAppliedValues.forEach(function (entry) { entry[0].value = entry[1]; });
        });
    }

    var csvForm = document.querySelector('[data-checador-csv-form]');
    var punchReportFilterForm = document.getElementById('punch-report-filter-form');
    if (!csvForm || !punchReportFilterForm) return;

    csvForm.addEventListener('submit', function () {
        ['dt_search', 'dt_order_column', 'dt_order_direction'].forEach(function (name) {
            var source = punchReportFilterForm.querySelector('[name="' + name + '"]');
            var target = csvForm.querySelector('[name="' + name + '"]');
            if (source && target) target.value = source.value;
        });
    });
});
