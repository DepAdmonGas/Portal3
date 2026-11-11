(function () {
    'use strict';

    var attendanceUrl = '/departamento-operativo/recursos-humanos/checador/asistencia';

    function showSearchError(root, message) {
        var errorElement = root && root.querySelector('[data-checador-search-error]');
        if (!errorElement) return;
        errorElement.textContent = message;
        errorElement.classList.remove('d-none');
        errorElement.classList.add('alert', 'alert-danger', 'py-2');
        errorElement.hidden = false;
        errorElement.style.display = 'block';
    }

    function errorMessage(error) {
        var status = error && error.response ? Number(error.response.status || 0) : 0;
        var contentType = error && error.response && error.response.headers
            ? String(error.response.headers['content-type'] || '').toLowerCase()
            : '';
        if (status === 401 || (status === 200 && contentType.indexOf('text/html') !== -1)) {
            return 'La sesión no está disponible. Inicia sesión nuevamente y vuelve a intentar la búsqueda.';
        }
        if (status === 403) return 'No se autorizó la consulta para el centro de trabajo seleccionado.';
        if (status === 419) return 'La sesión venció. Recarga la página e inténtalo de nuevo.';
        if (status === 422) return 'La fecha o el colaborador seleccionado no son válidos.';
        if (error && error.code === 'axios_unavailable') return 'No se pudo iniciar la búsqueda. Recarga la página e inténtalo de nuevo.';
        if (error && error.code === 'csrf_unavailable') return 'La sesión no está disponible. Recarga la página e inténtalo de nuevo.';
        if (error && error.code === 'attendance_result_update_failed') return 'No fue posible actualizar los resultados. Los resultados anteriores permanecen visibles; puedes reintentar.';
        if (error && error.code === 'attendance_response_invalid') return 'No fue posible actualizar los resultados. Los resultados anteriores permanecen visibles; puedes reintentar.';
        return 'No fue posible consultar la asistencia. Intenta nuevamente; tus resultados anteriores permanecen visibles.';
    }

    document.addEventListener('alpine:init', function () {
        Alpine.data('checadorAttendanceSearch', function () {
            return {
                fecha: '',
                colaborador: '',
                fechaAplicada: '',
                colaboradorAplicado: '',
                cargando: false,
                error: '',

                init: function () {
                    var controls = this.$el.querySelector('[data-checador-filter-controls]');
                    if (controls) {
                        var dateInput = controls.querySelector('input[name="date"]');
                        var workerSelect = controls.querySelector('select[name="worker_id"]');
                        this.fecha = dateInput ? dateInput.value : '';
                        this.colaborador = workerSelect ? workerSelect.value : '';
                        this.fechaAplicada = this.fecha;
                        this.colaboradorAplicado = this.colaborador;
                    }

                    var modal = this.$el.querySelector('[data-checador-filter-modal]');
                    var component = this;
                    this.bindModalSelect2({
                        modalRef: 'checador-filter-modal',
                        selectRef: 'checadorWorkerSelect',
                        wrapperRef: 'checadorWorkerSelectWrapper',
                        model: 'colaborador',
                        options: {
                            dropdownParent: window.jQuery('#checador-filter-modal'),
                            width: '100%',
                            placeholder: 'Todos los colaboradores',
                            minimumResultsForSearch: 0
                        },
                        namespace: 'checadorAttendanceWorker'
                    });
                    if (modal) {
                        modal.addEventListener('show.bs.modal', function () {
                            component.prepareDraft();
                        });
                        modal.addEventListener('hidden.bs.modal', function () {
                            component.discardDraft();
                        });
                    }

                },

                prepareDraft: function () {
                    this.fecha = this.fechaAplicada;
                    this.colaborador = this.colaboradorAplicado;
                    this.error = '';
                },

                discardDraft: function () {
                    this.fecha = this.fechaAplicada;
                    this.colaborador = this.colaboradorAplicado;
                    this.error = '';
                },

                clearFilters: function () {
                    return this.search(true);
                },

                search: async function (clearRequested) {
                    var root = this.$el;
                    var isClear = clearRequested === true;
                    if (this.cargando) {
                        return;
                    }

                    var controls = root.querySelector('[data-checador-filter-controls]');
                    if (!controls) {
                        this.error = 'No se encontraron los filtros de búsqueda. Recarga la página e inténtalo de nuevo.';
                        return;
                    }

                    var dateInput = controls.querySelector('input[name="date"]');
                    if (!isClear && (!dateInput || !dateInput.checkValidity())) {
                        if (dateInput && typeof dateInput.reportValidity === 'function') dateInput.reportValidity();
                        this.error = 'Selecciona una fecha laboral antes de buscar.';
                        return;
                    }

                    if (!window.axios || typeof window.axios.post !== 'function') {
                        this.error = errorMessage({ code: 'axios_unavailable' });
                        return;
                    }

                    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                    if (!csrfToken) {
                        this.error = errorMessage({ code: 'csrf_unavailable' });
                        return;
                    }

                    var payload = {
                        date: isClear ? null : this.fecha,
                        worker_id: isClear || this.colaborador === '' ? null : this.colaborador
                    };
                    this.cargando = true;
                    this.error = '';

                    var previousResults = root.querySelector('[data-checador-attendance-results]');
                    var previousTable = previousResults && previousResults.querySelector('#checador-daily-attendance-table');
                    var oldRootState = {
                        attendanceState: root.dataset.attendanceState,
                        emptyMessage: root.dataset.attendanceEmptyMessage
                    };
                    var previousWorkerSelect = controls.querySelector('select[name="worker_id"]');
                    try {
                        var response = await window.axios.post(attendanceUrl, payload, {
                            responseType: 'text',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            // Keep this read request's 419 in the component so
                            // the shared Axios interceptor cannot reload the page.
                            validateStatus: function (status) {
                                return (status >= 200 && status < 300) || status === 419;
                            }
                        });
                        var contentType = response && response.headers
                            ? String(response.headers['content-type'] || '')
                            : '';
                        var responseText = typeof response.data === 'string' ? response.data : '';
                        if (contentType.toLowerCase().indexOf('application/json') === -1
                            || (responseText === '' && (!response.data || typeof response.data !== 'object'))) {
                            var formatError = new Error('attendance_response_invalid');
                            formatError.code = 'attendance_response_invalid';
                            formatError.response = response;
                            throw formatError;
                        }

                        var result = response.data;
                        if (typeof result === 'string') {
                            try {
                                result = JSON.parse(result);
                            } catch (parseError) {
                                var jsonError = new Error('attendance_response_invalid');
                                jsonError.code = 'attendance_response_invalid';
                                jsonError.response = response;
                                throw jsonError;
                            }
                        }
                        if (Number(response.status) === 419 && result && typeof result.new_token === 'string' && csrfMeta) {
                            csrfMeta.setAttribute('content', result.new_token);
                        }
                        if (!result || result.success !== true || typeof result.html !== 'string') {
                            var serverError = new Error('attendance_response_invalid');
                            serverError.code = 'attendance_response_invalid';
                            serverError.response = { status: response.status, headers: response.headers, data: result };
                            throw serverError;
                        }

                        var parsedDocument = new DOMParser().parseFromString(result.html, 'text/html');
                        var nextRoot = parsedDocument.querySelector('#container');
                        var nextResults = nextRoot && nextRoot.querySelector('[data-checador-attendance-results]');
                        var nextTable = nextResults && nextResults.querySelector('#checador-daily-attendance-table');
                        var nextHeaderCount = nextTable && nextTable.tHead && nextTable.tHead.rows[0]
                            ? nextTable.tHead.rows[0].cells.length
                            : 0;
                        var nextControls = nextRoot && nextRoot.querySelector('[data-checador-filter-controls]');
                        var nextDateInput = nextControls && nextControls.querySelector('input[name="date"]');
                        var nextWorkerSelect = nextControls && nextControls.querySelector('select[name="worker_id"]');
                        if (!nextRoot || !nextResults || !nextTable || nextHeaderCount !== 9 || !nextDateInput || !nextWorkerSelect || !previousResults) {
                            var fragmentError = new Error('attendance_response_invalid');
                            fragmentError.code = 'attendance_response_invalid';
                            throw fragmentError;
                        }

                        if (previousTable && window.jQuery && jQuery.fn && jQuery.fn.DataTable
                            && jQuery.fn.DataTable.isDataTable(previousTable)) {
                            jQuery(previousTable).DataTable().destroy();
                        }
                        previousResults.replaceWith(nextResults);
                        var dataTableUpdated = typeof window.initChecadorAttendanceDataTable === 'function'
                            && window.initChecadorAttendanceDataTable(nextResults) === true;
                        if (!dataTableUpdated) {
                            var insertedTable = nextResults.querySelector('#checador-daily-attendance-table');
                            if (insertedTable && window.jQuery && jQuery.fn && jQuery.fn.DataTable
                                && jQuery.fn.DataTable.isDataTable(insertedTable)) {
                                jQuery(insertedTable).DataTable().destroy();
                            }
                            nextResults.replaceWith(previousResults);
                            if (previousTable && typeof window.initChecadorAttendanceDataTable === 'function') {
                                window.initChecadorAttendanceDataTable(previousResults);
                            }
                            root.dataset.attendanceState = oldRootState.attendanceState || '';
                            root.dataset.attendanceEmptyMessage = oldRootState.emptyMessage || '';
                            var updateError = new Error('attendance_result_update_failed');
                            updateError.code = 'attendance_result_update_failed';
                            throw updateError;
                        }

                        var currentDateInput = controls.querySelector('input[name="date"]');
                        if (currentDateInput) currentDateInput.value = nextDateInput.value;
                        if (previousWorkerSelect) {
                            previousWorkerSelect.replaceChildren.apply(previousWorkerSelect, Array.from(nextWorkerSelect.options).map(function (option) {
                                return option.cloneNode(true);
                            }));
                            previousWorkerSelect.disabled = nextWorkerSelect.disabled;
                            previousWorkerSelect.value = nextWorkerSelect.value;
                        }
                        root.dataset.attendanceState = nextRoot.dataset.attendanceState || '';
                        root.dataset.attendanceEmptyMessage = nextRoot.dataset.attendanceEmptyMessage || '';
                        this.fecha = nextDateInput.value;
                        this.colaborador = nextWorkerSelect.value;
                        this.fechaAplicada = this.fecha;
                        this.colaboradorAplicado = this.colaborador;
                        await this.closeFilterModal(root);
                        this.cargando = false;
                        this.error = '';
                    } catch (error) {
                        this.cargando = false;
                        this.error = errorMessage(error);
                    }
                },

                closeFilterModal: function (root) {
                    var modal = root.querySelector('[data-checador-filter-modal]');
                    if (!modal || !modal.classList.contains('show') || !window.bootstrap || !window.bootstrap.Modal) {
                        return Promise.resolve();
                    }
                    return new Promise(function (resolve) {
                        var done = false;
                        var finish = function () {
                            if (done) return;
                            done = true;
                            resolve();
                        };
                        modal.addEventListener('hidden.bs.modal', finish, { once: true });
                        window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                        window.setTimeout(finish, 700);
                    });
                }
            };
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-checador-search-action]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var root = button.closest('#container');
                var alpineState = root && root._x_dataStack ? root._x_dataStack[0] : null;
                var action = button.getAttribute('data-checador-search-action');
                var handlerReady = action === 'clear'
                    ? alpineState && typeof alpineState.clearFilters === 'function'
                    : alpineState && typeof alpineState.search === 'function';
                if (handlerReady && window.Alpine) {
                    event.preventDefault();
                    try {
                        var result = action === 'clear'
                            ? alpineState.clearFilters()
                            : alpineState.search();
                        if (result && typeof result.catch === 'function') {
                            result.catch(function () {
                                if (!alpineState.error) {
                                    alpineState.error = 'No fue posible consultar la asistencia. Intenta nuevamente; tus resultados anteriores permanecen visibles.';
                                }
                            });
                        }
                        return result;
                    } catch (error) {
                        alpineState.error = 'No fue posible consultar la asistencia. Intenta nuevamente; tus resultados anteriores permanecen visibles.';
                    }
                    return;
                }
                event.preventDefault();
                var message = window.Alpine
                    ? 'No se pudo iniciar el componente de búsqueda. Recarga la página e inténtalo de nuevo.'
                    : 'La búsqueda interactiva no está disponible porque Alpine.js no se cargó. Recarga la página e inténtalo de nuevo.';
                showSearchError(root, message);
            });
        });

        // Keep native GET filter behavior only for the separate legacy punch report.
        // Daily attendance uses Alpine buttons and has no submit/reset handler.
        document.querySelectorAll('[data-checador-filter-form]').forEach(function (filterForm) {
            var root = filterForm.closest('#container');
            filterForm.addEventListener('invalid', function (event) {
                var alpineState = root && root._x_dataStack ? root._x_dataStack[0] : null;
                if (alpineState && typeof alpineState === 'object') {
                    alpineState.error = 'Selecciona una fecha laboral antes de buscar.';
                } else {
                    showSearchError(root, 'Selecciona una fecha laboral antes de buscar.');
                }
            }, true);

            filterForm.addEventListener('submit', function (event) {
                if (filterForm.hasAttribute('data-checador-ajax')) {
                    if (root && root._x_dataStack && window.Alpine) return;
                    event.preventDefault();
                    var message = window.Alpine
                        ? 'No se pudo iniciar el componente de búsqueda. Recarga la página e inténtalo de nuevo.'
                        : 'La búsqueda interactiva no está disponible porque Alpine.js no se cargó. Recarga la página e inténtalo de nuevo.';
                    showSearchError(root, message);
                    return;
                }

                if (event.submitter && event.submitter.hasAttribute('data-checador-clear-filters')) {
                    event.preventDefault();
                    window.location.assign(filterForm.action);
                    return;
                }

                var dateInput = filterForm.querySelector('input[name="date"]');
                if (dateInput && dateInput.value === '') dateInput.disabled = true;
                var workerSelect = filterForm.querySelector('select[name="worker_id"]');
                if (workerSelect && workerSelect.value === '') workerSelect.disabled = true;
            });
        });

        var csvForm = document.querySelector('[data-checador-csv-form]');
        var filterForm = document.getElementById('punch-report-filter-form');
        if (!csvForm || !filterForm) return;

        csvForm.addEventListener('submit', function () {
            ['dt_search', 'dt_order_column', 'dt_order_direction'].forEach(function (name) {
                var source = filterForm.querySelector('[name="' + name + '"]');
                var target = csvForm.querySelector('[name="' + name + '"]');
                if (source && target) target.value = source.value;
            });
        });
    });
})();
