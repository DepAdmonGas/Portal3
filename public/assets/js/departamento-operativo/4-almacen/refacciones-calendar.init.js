document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('refacciones-module');
    const target = document.getElementById('refacciones-calendar');
    if (!root || !target) return;

    if (!window.FullCalendar) {
        target.innerHTML = '<div class="alert alert-danger">No fue posible cargar el calendario. Recarga la página.</div>';
        console.error('[Refacciones] FullCalendar no está disponible.');
        return;
    }

    let events = [];
    try {
        events = JSON.parse(root.dataset.calendarEvents || '[]');
    } catch (error) {
        console.error('[Refacciones] No se pudieron leer los eventos del calendario.', error);
    }

    const eventTone = (state) => {
        const normalized = String(state || 'pendiente').toLowerCase();
        if (['finalizado', 'finalizada', 'completado', 'completada'].includes(normalized)) return 'success';
        if (['cancelado', 'cancelada'].includes(normalized)) return 'danger';
        return 'warning';
    };
    const withEventStyle = (event) => ({
        ...event,
        classNames: ['event-fc-color', `fc-bg-${eventTone(event.extendedProps?.estado)}`],
    });
    const typeNames = {
        compra: 'Compras programadas',
        mantenimiento_externo: 'Mantenimientos por proveedores externos',
        prueba: 'Registro, preparación y pruebas',
        calibracion: 'Calibraciones',
    };
    const stateNames = {
        pendiente: 'Pendiente',
        finalizado: 'Finalizado',
        finalizada: 'Finalizado',
        completado: 'Finalizado',
        completada: 'Finalizado',
        cancelado: 'Cancelado',
        cancelada: 'Cancelado',
    };
    const dayModalElement = document.getElementById('modalRefaccionesDia');
    const detailModalElement = document.getElementById('modalRefaccionesDetalle');
    const dayModal = dayModalElement ? bootstrap.Modal.getOrCreateInstance(dayModalElement) : null;
    const detailModal = detailModalElement ? bootstrap.Modal.getOrCreateInstance(detailModalElement) : null;
    const dayTitle = dayModalElement?.querySelector('[data-calendar-day-title]');
    const dayEventsBody = dayModalElement?.querySelector('[data-calendar-day-events]');
    const detailBackButton = detailModalElement?.querySelector('[data-calendar-detail-back]');
    const detailEditButton = detailModalElement?.querySelector('[data-calendar-detail-edit]');
    const editModalElement = document.getElementById('modalRefaccionesEditarEstado');
    const editModal = editModalElement ? bootstrap.Modal.getOrCreateInstance(editModalElement) : null;
    const editStatusSelect = editModalElement?.querySelector('[data-calendar-edit-status]');
    const editSaveButton = editModalElement?.querySelector('[data-calendar-edit-save]');
    const editBackButton = editModalElement?.querySelector('[data-calendar-edit-back]');
    const editCloseButton = editModalElement?.querySelector('[data-calendar-edit-close]');
    let returnToDayOnDetailClose = false;
    let openEditAfterDetailClose = false;
    let resumeDetailAfterEditClose = false;
    let closeDetailAfterEditClose = false;
    let selectedDay = '';
    let currentDetail = null;

    const notifyCalendarError = (message) => {
        if (window.Notify && typeof Notify.error === 'function') Notify.error(message);
        else if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'error', text: message, showConfirmButton: false, timer: 3500 });
        else window.alert(message);
    };
    const formatCalendarDate = (value, options = { dateStyle: 'long' }) => {
        if (!value) return 'Sin información';
        const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
        return Number.isNaN(date.getTime()) ? 'Sin información' : new Intl.DateTimeFormat('es-MX', options).format(date);
    };
    const formatCalendarTime = (value) => {
        const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
        if (!match) return 'Sin información';
        const hour = Number(match[1]);
        const minutes = match[2];
        if (hour > 23) return 'Sin información';
        return `${hour % 12 || 12}:${minutes} ${hour >= 12 ? 'PM' : 'AM'}`;
    };
    const notifyCalendarSuccess = (message) => {
        if (window.Notify && typeof Notify.success === 'function') Notify.success(message);
        else if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', text: message, showConfirmButton: false, timer: 3000 });
    };
    const reminderButton = document.querySelector('[data-calendar-send-reminders]');
    reminderButton?.addEventListener('click', async () => {
        if (reminderButton.disabled) return;
        const originalContent = reminderButton.innerHTML;
        reminderButton.disabled = true;
        reminderButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Enviando…';
        window.loader?.show();
        try {
            const response = await fetch(reminderButton.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible enviar los recordatorios.');
            notifyCalendarSuccess(result.message || 'Los recordatorios se enviaron correctamente.');
        } catch (error) {
            notifyCalendarError(error.message || 'No fue posible enviar los recordatorios.');
        } finally {
            window.loader?.hide();
            reminderButton.disabled = false;
            reminderButton.innerHTML = originalContent;
        }
    });
    const renderDetailState = (state) => {
        const badge = detailModalElement.querySelector('[data-calendar-detail-state]');
        const tone = eventTone(state);
        badge.className = `badge text-white ${tone === 'success' ? 'bg-success' : tone === 'danger' ? 'bg-danger' : 'bg-warning'}`;
        badge.textContent = stateNames[String(state || '').toLowerCase()] || 'Pendiente';
    };
    const renderDayEvents = (items) => {
        if (!dayEventsBody) return;
        dayEventsBody.replaceChildren();
        if (!items.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'text-center text-muted py-5';
            cell.textContent = 'No hay eventos programados para este día.';
            row.append(cell);
            dayEventsBody.append(row);
            return;
        }

        items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'pointer';
            row.tabIndex = 0;
            row.setAttribute('role', 'button');
            const number = document.createElement('td');
            number.className = 'text-center';
            number.textContent = String(index + 1);
            const title = document.createElement('td');
            const eventName = document.createElement('strong');
            eventName.textContent = item.nombre || 'Sin título';
            title.append(eventName);
            if (item.ubicacion) {
                const location = document.createElement('div');
                location.className = 'small text-muted mt-1';
                location.textContent = item.ubicacion;
                title.append(location);
            }
            const typeCell = document.createElement('td');
            typeCell.className = 'text-center';
            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge bg-primary text-white';
            typeBadge.textContent = typeNames[item.tipo] || 'Evento';
            typeCell.append(typeBadge);
            const stateCell = document.createElement('td');
            stateCell.className = 'text-center';
            const stateBadge = document.createElement('span');
            stateBadge.className = `badge text-white ${eventTone(item.estado) === 'success' ? 'bg-success' : eventTone(item.estado) === 'danger' ? 'bg-danger' : 'bg-warning'}`;
            stateBadge.textContent = stateNames[String(item.estado || '').toLowerCase()] || 'Pendiente';
            stateCell.append(stateBadge);
            row.append(number, title, typeCell, stateCell);
            row.addEventListener('click', () => openEventDetail(item.id, true));
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openEventDetail(item.id, true);
                }
            });
            dayEventsBody.append(row);
        });
    };
    const openEventDetail = async (id, fromDay = false) => {
        try {
            const response = await fetch(`${root.dataset.baseUrl}/calendario/detalle?id=${encodeURIComponent(id)}`, {
                headers: { 'Accept': 'application/json' },
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.detalle) throw new Error(result.message || 'No fue posible cargar el detalle del evento.');
            const detail = result.detalle;
            currentDetail = detail;
            detailModalElement.querySelectorAll('[data-calendar-detail]').forEach((field) => {
                const key = field.dataset.calendarDetail;
                const value = detail[key];
                field.textContent = key === 'tipo'
                    ? (typeNames[value] || 'Evento')
                    : key === 'fecha'
                        ? formatCalendarDate(value)
                    : key === 'hora'
                        ? formatCalendarTime(value)
                        : (value || 'Sin información');
            });
            renderDetailState(detail.estado);
            detailModalElement.querySelector('[data-calendar-detail-time-wrap]').classList.toggle('d-none', !detail.hora);
            detailModalElement.querySelector('[data-calendar-detail-category-wrap]').classList.toggle('d-none', !detail.categoria);
            detailModalElement.querySelector('[data-calendar-detail-provider-wrap]').classList.toggle('d-none', !detail.proveedor_externo);
            detailBackButton.classList.toggle('d-none', !fromDay);
            detailEditButton.classList.toggle('d-none', detailEditButton.dataset.calendarEditable !== '1' || String(detail.estado || '').toLowerCase() !== 'pendiente');

            if (fromDay && dayModalElement.classList.contains('show')) {
                dayModalElement.addEventListener('hidden.bs.modal', () => detailModal.show(), { once: true });
                dayModal.hide();
            } else {
                detailModal.show();
            }
        } catch (error) {
            notifyCalendarError(error.message || 'No fue posible cargar el detalle del evento.');
        }
    };

    dayModalElement?.addEventListener('hidden.bs.modal', () => {
        if (selectedDay) calendar.gotoDate(selectedDay);
    });
    detailModalElement?.addEventListener('hidden.bs.modal', () => {
        if (returnToDayOnDetailClose) {
            returnToDayOnDetailClose = false;
            dayModal?.show();
        } else if (openEditAfterDetailClose) {
            openEditAfterDetailClose = false;
            editModal?.show();
        }
    });
    detailBackButton?.addEventListener('click', () => {
        returnToDayOnDetailClose = true;
        detailModal?.hide();
    });
    detailEditButton?.addEventListener('click', () => {
        editStatusSelect.value = '';
        editStatusSelect.classList.remove('is-invalid');
        openEditAfterDetailClose = true;
        detailModal?.hide();
    });
    editModalElement?.addEventListener('hidden.bs.modal', () => {
        if (resumeDetailAfterEditClose) {
            resumeDetailAfterEditClose = false;
            detailModal?.show();
        } else if (closeDetailAfterEditClose) {
            closeDetailAfterEditClose = false;
            detailModal?.hide();
        }
    });
    editBackButton?.addEventListener('click', () => {
        resumeDetailAfterEditClose = true;
        editModal?.hide();
    });
    editCloseButton?.addEventListener('click', () => {
        closeDetailAfterEditClose = true;
        editModal?.hide();
    });
    editSaveButton?.addEventListener('click', async () => {
        const nextState = editStatusSelect.value;
        if (!currentDetail || !['finalizado', 'cancelado'].includes(nextState)) {
            editStatusSelect.classList.add('is-invalid');
            notifyCalendarError('Selecciona si el evento quedó finalizado o cancelado.');
            editStatusSelect.focus();
            return;
        }

        const originalContent = editSaveButton.innerHTML;
        editSaveButton.disabled = true;
        editSaveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`${root.dataset.baseUrl}/calendario/estado`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ id: currentDetail.id, estado: nextState }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible actualizar el estado del evento.');

            const previousState = String(currentDetail.estado || 'pendiente').toLowerCase();
            currentDetail.estado = result.estado || nextState;
            renderDetailState(currentDetail.estado);
            detailEditButton.classList.add('d-none');

            const calendarEvent = calendar.getEventById(String(currentDetail.id));
            if (calendarEvent) {
                calendarEvent.setExtendedProp('estado', currentDetail.estado);
                calendarEvent.setProp('classNames', ['event-fc-color', `fc-bg-${eventTone(currentDetail.estado)}`]);
            }
            const totals = {
                pendientes: Number(document.querySelector('[data-calendar-total="pendientes"]')?.textContent || 0),
                finalizados: Number(document.querySelector('[data-calendar-total="finalizados"]')?.textContent || 0),
                cancelados: Number(document.querySelector('[data-calendar-total="cancelados"]')?.textContent || 0),
            };
            if (previousState === 'pendiente') totals.pendientes = Math.max(0, totals.pendientes - 1);
            if (currentDetail.estado === 'finalizado') totals.finalizados += 1;
            if (currentDetail.estado === 'cancelado') totals.cancelados += 1;
            Object.entries(totals).forEach(([key, value]) => {
                const node = document.querySelector(`[data-calendar-total="${key}"]`);
                if (node) node.textContent = String(value);
            });
            if (selectedDay) {
                try {
                    const dayResponse = await fetch(`${root.dataset.baseUrl}/calendario/dia?fecha=${encodeURIComponent(selectedDay)}`, { headers: { 'Accept': 'application/json' } });
                    const dayResult = await dayResponse.json();
                    if (dayResponse.ok && dayResult.success) renderDayEvents(dayResult.eventos || []);
                } catch (refreshError) {
                    console.warn('[Refacciones] No se pudo refrescar la lista del día.', refreshError);
                }
            }
            resumeDetailAfterEditClose = true;
            editModal?.hide();
            notifyCalendarSuccess(result.message || 'El estado del evento se actualizó. Ya no se puede editar.');
        } catch (error) {
            notifyCalendarError(error.message || 'No fue posible actualizar el estado del evento.');
        } finally {
            editSaveButton.disabled = false;
            editSaveButton.innerHTML = originalContent;
        }
    });

    const calendar = new FullCalendar.Calendar(target, {
        locale: 'es',
        initialView: window.innerWidth <= 1199 ? 'listWeek' : 'dayGridMonth',
        firstDay: 0,
        fixedWeekCount: false,
        height: window.innerWidth <= 1199 ? 900 : 1052,
        dayMaxEvents: true,
        moreLinkText: (count) => `+${count} más`,
        displayEventTime: false,
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        buttonText: { today: 'Hoy' },
        dayHeaderFormat: { weekday: 'long' },
        events: events.map(withEventStyle),
        dateClick(info) {
            selectedDay = info.dateStr;
            dayTitle.textContent = formatCalendarDate(selectedDay, { dateStyle: 'full' });
            dayEventsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando eventos…</td></tr>';
            dayModal.show();
            fetch(`${root.dataset.baseUrl}/calendario/dia?fecha=${encodeURIComponent(selectedDay)}`, {
                headers: { 'Accept': 'application/json' },
            })
                .then(async (response) => {
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible cargar los eventos del día.');
                    renderDayEvents(result.eventos || []);
                })
                .catch((error) => {
                    renderDayEvents([]);
                    notifyCalendarError(error.message || 'No fue posible cargar los eventos del día.');
                });
        },
        eventDidMount(info) {
            info.el.title = info.event.title;
        },
        eventClick(info) {
            openEventDetail(info.event.id);
        },
    });

    calendar.render();

    const form = document.querySelector('[data-refacciones-calendar-form]');
    if (!form) return;

    const typeSelect = form.querySelector('[name="tipo_evento"]');
    const fieldsContainer = form.querySelector('[data-calendar-fields]');
    const saveButton = form.querySelector('[data-calendar-save]');
    const modalElement = form.closest('.modal');
    const typeFields = {
        categoria: ['compra'],
        proveedor_externo: ['mantenimiento_externo'],
        hora: ['calibracion'],
    };
    const dateColumn = form.querySelector('[data-calendar-date-column]');
    const singleLocation = form.querySelector('[data-calendar-single-location]');
    const multipleLocations = form.querySelector('[data-calendar-multiple-locations]');
    const locationWrapper = form.querySelector('[data-calendar-location-select]');
    const locationLabel = locationWrapper?.querySelector('label[for]');

    if (multipleLocations && window.jQuery && jQuery.fn.select2) {
        const $locations = jQuery(multipleLocations);
        $locations.select2({
            width: '100%',
            language: 'es',
            closeOnSelect: false,
            dropdownParent: jQuery(modalElement),
            placeholder: 'Selecciona una o varias estaciones o departamentos',
        });
        $locations.on('change.refaccionesCalendar', () => multipleLocations.classList.remove('is-invalid'));
    }

    const setFieldVisibility = () => {
        const type = typeSelect.value;
        const isPurchase = type === 'compra';
        const showTime = (typeFields.hora || []).includes(type);
        fieldsContainer.classList.toggle('d-none', type === '');
        dateColumn?.classList.toggle('col-md-6', showTime);
        if (locationWrapper && multipleLocations && singleLocation) {
            const useMultiple = isPurchase && !form.querySelector('[data-calendar-location-fixed]');
            singleLocation.classList.toggle('d-none', useMultiple);
            multipleLocations.classList.toggle('d-none', !useMultiple);
            if (locationLabel) locationLabel.htmlFor = useMultiple ? multipleLocations.id : singleLocation.id;
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(multipleLocations).next('.select2-container').toggleClass('d-none', !useMultiple);
            }
            singleLocation.toggleAttribute('data-required', !useMultiple && type !== '' && !form.querySelector('[data-calendar-location-fixed]'));
            singleLocation.required = !useMultiple && type !== '' && !form.querySelector('[data-calendar-location-fixed]');
            multipleLocations.toggleAttribute('data-required', useMultiple);
            multipleLocations.required = useMultiple;
            if (window.jQuery && jQuery.fn.select2) jQuery(multipleLocations).trigger('change.select2');
        }
        form.querySelectorAll('[data-calendar-field]').forEach((wrapper) => {
            const name = wrapper.dataset.calendarField;
            const visible = (typeFields[name] || []).includes(type);
            wrapper.classList.toggle('d-none', !visible);
            const control = wrapper.querySelector('input,select,textarea');
            if (control) {
                control.toggleAttribute('data-required', visible);
                control.required = visible;
                if (!visible) {
                    control.value = '';
                    control.classList.remove('is-invalid');
                }
            }
        });
        saveButton.disabled = type === '';
        typeSelect.classList.remove('is-invalid');
    };
    typeSelect.addEventListener('change', () => {
        if (singleLocation) singleLocation.value = '';
        if (multipleLocations) {
            if (window.jQuery && jQuery.fn.select2) jQuery(multipleLocations).val(null).trigger('change');
            else [...multipleLocations.options].forEach((option) => { option.selected = false; });
        }
        setFieldVisibility();
    });

    const notify = (kind, message) => {
        const method = kind === 'success' ? 'success' : 'error';
        if (window.Notify && typeof Notify[method] === 'function') Notify[method](message);
        else if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: kind, text: message, showConfirmButton: false, timer: 3500 });
    };
    const updateTotals = (event) => {
        const totals = {
            pendientes: Number(document.querySelector('[data-calendar-total="pendientes"]')?.textContent || 0),
            finalizados: Number(document.querySelector('[data-calendar-total="finalizados"]')?.textContent || 0),
            cancelados: Number(document.querySelector('[data-calendar-total="cancelados"]')?.textContent || 0),
            total: Number(document.querySelector('[data-calendar-total="total"]')?.textContent || 0),
        };
        const state = String(event.extendedProps?.estado || 'pendiente').toLowerCase();
        if (state === 'pendiente') totals.pendientes += 1;
        else if (['finalizado', 'finalizada', 'completado', 'completada'].includes(state)) totals.finalizados += 1;
        else if (['cancelado', 'cancelada'].includes(state)) totals.cancelados += 1;
        totals.total += 1;
        Object.entries(totals).forEach(([key, value]) => {
            const node = document.querySelector(`[data-calendar-total="${key}"]`);
            if (node) node.textContent = String(value);
        });
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-required]')) event.target.classList.remove('is-invalid');
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-required]')) event.target.classList.remove('is-invalid');
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (saveButton.disabled) return;

        const required = [...form.querySelectorAll('[data-required]')];
        const invalid = required.filter((control) => {
            const hasValue = control.multiple
                ? [...control.selectedOptions].length > 0
                : String(control.value || '').trim() !== '';
            return !hasValue || !control.checkValidity();
        });
        required.forEach((control) => control.classList.toggle('is-invalid', invalid.includes(control)));
        if (invalid.length) {
            notify('error', 'Completa los campos obligatorios señalados.');
            invalid[0].focus();
            return;
        }

        const originalButton = saveButton.innerHTML;
        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';
        try {
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            if (payload.tipo_evento === 'compra' && multipleLocations && !multipleLocations.classList.contains('d-none')) {
                payload.id_estaciones = formData.getAll('id_estaciones[]');
                delete payload.id_estacion;
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible guardar el evento.');

            const typeNames = {
                compra: 'Compras programadas',
                mantenimiento_externo: 'Mantenimientos por proveedores externos',
                prueba: 'Registro, preparación y pruebas',
                calibracion: 'Calibraciones',
            };
            const selectedLocations = payload.id_estaciones
                ? [...(multipleLocations?.selectedOptions || [])].map((option) => option.textContent.trim())
                : [];
            const selectedLocation = singleLocation;
            const locationName = form.querySelector('[data-calendar-location-name]')?.textContent.trim()
                || (selectedLocations.length ? selectedLocations.join(', ') : selectedLocation?.options[selectedLocation.selectedIndex]?.text)
                || '';
            const eventRecord = {
                id: String(result.id),
                title: payload.titulo,
                start: payload.hora ? `${payload.fecha}T${payload.hora}` : payload.fecha,
                allDay: !payload.hora,
                extendedProps: {
                    tipo: payload.tipo_evento,
                    categoria: payload.categoria || '',
                    proveedor_externo: payload.proveedor_externo || '',
                    ubicacion: locationName,
                    estado: 'pendiente',
                    hora: payload.hora || '',
                    responsable: payload.correo_responsable || '',
                    notas: payload.notas || '',
                },
            };
            const addedEvent = calendar.addEvent(withEventStyle(eventRecord));
            updateTotals(addedEvent);
            calendar.gotoDate(payload.fecha);
            bootstrap.Modal.getInstance(modalElement)?.hide();
            form.reset();
            form.querySelectorAll('.is-invalid').forEach((control) => control.classList.remove('is-invalid'));
            setFieldVisibility();
            notify('success', result.message || `${typeNames[payload.tipo_evento]}: evento guardado.`);
        } catch (error) {
            notify('error', error.message || 'No fue posible guardar el evento.');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = originalButton;
            setFieldVisibility();
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach((control) => control.classList.remove('is-invalid'));
        setFieldVisibility();
    });
    setFieldVisibility();
});
