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

        function showError(message) {
            if (!errorElement) return;
            errorElement.textContent = message;
            errorElement.classList.remove('d-none');
        }

        function resetDraft() {
            if (dateInput) dateInput.value = appliedDate;
            if (workerInput) workerInput.value = appliedWorker;
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
                workerInput.value = '';
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
                if (!nextResults || !currentResults) {
                    throw new Error('invalid_fragment');
                }
                if (nextResults.dataset.workplaceId !== currentResults.dataset.workplaceId) {
                    clearResultsForUnverifiedContext('Cambió el centro autorizado. Recarga la página para confirmar el contexto antes de consultar nuevamente.');
                    throw new Error('attendance_context_changed');
                }
                if (nextResults.dataset.attendanceState !== 'success'
                    || !nextTable || !nextDate || !nextWorker) {
                    throw new Error('invalid_fragment');
                }

                if (currentTable && window.jQuery && jQuery.fn && jQuery.fn.DataTable
                    && jQuery.fn.DataTable.isDataTable(currentTable)) {
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
                    workerInput.value = appliedWorker;
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
