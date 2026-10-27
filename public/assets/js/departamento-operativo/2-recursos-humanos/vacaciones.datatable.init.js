(function () {
    'use strict';

    const root = document.getElementById('vacaciones-container');
    const tableElement = document.getElementById('table-vacaciones');
    if (!root || !tableElement || !window.jQuery || !$.fn.DataTable) return;

    const year = Number(root.dataset.year) || new Date().getFullYear();
    const selector = document.getElementById('module-station-selector-vacaciones');
    const pendingBadge = document.getElementById('vacaciones-pendientes-count');
    const newButton = document.getElementById('vacaciones-nuevo');
    const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-vacaciones-detalle'));
    const historyModalElement = document.getElementById('modal-vacaciones-historial');
    const historyModal = bootstrap.Modal.getOrCreateInstance(historyModalElement);
    const detailContent = document.getElementById('vacaciones-detalle-content');
    const detailLoading = document.getElementById('vacaciones-detalle-loading');
    const historyBody = document.getElementById('vacaciones-historial-body');
    const historyLoading = document.getElementById('vacaciones-historial-loading');
    const historyTitle = document.getElementById('vacaciones-historial-title');
    const commentCanvas = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('vacaciones-comentarios'));
    const commentList = document.getElementById('vacaciones-comentarios-list');
    const commentDetail = document.getElementById('vacaciones-comentarios-detalle');
    const commentInput = document.getElementById('vacaciones-comentario-input');
    const commentForm = document.getElementById('vacaciones-comentario-form');
    const sendingIndicator = document.getElementById('vacaciones-enviando');
    let activeComment = null;
    let activeHistory = null;
    let returnToHistory = false;
    let detailFromHistory = false;
    let sending = false;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));

    const getContextId = () => {
        if (!selector) return Number(root.dataset.contextoSeleccionado || 0);
        if (!selector.value) return 0;
        const match = selector.value.match(/^(?:estacion|depto)_(\d+)$/);
        return match ? Number(match[1]) : 0;
    };

    const syncNewButton = () => {
        if (!newButton) return;
        const locality = getContextId();
        const visible = root.dataset.puedeCrear === 'true' && locality > 0;
        newButton.classList.toggle('d-none', !visible);
        newButton.disabled = !visible;
        newButton.title = locality > 0 ? 'Crear solicitud para este contexto' : 'Selecciona una estación o departamento para crear una solicitud';
    };

    const stationColumnVisible = () => {
        if (!selector || selector.value !== '') return false;
        const values = Array.from(selector.options).filter((option) => /^(estacion|depto)_\d+$/.test(option.value));
        return values.length > 1;
    };

    const detailButton = (row) => '<a href="#" class="pointer text-primary js-vacaciones-historial d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px" data-id-personal="' + Number(row.id_personal) + '" data-nombre="' + escapeHtml(row.nombre_completo) + '" title="Historial de vacaciones"><i class="ti ti-eye fs-6"></i></a>';

    const signatureButton = (row) => {
        const signerByStatus = { 1: 19, 2: 2, 3: 354 };
        const requiredSigner = signerByStatus[Number(row.status)];
        if (!row.id_formulario || !requiredSigner || root.dataset.puedeFirmar !== 'true' || Number(root.dataset.idUsuario) !== requiredSigner) {
            return '<span class="d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="width:30px;height:30px" title="' + (!requiredSigner ? 'No hay firma pendiente' : 'Sin firma asignada') + '"><i class="ti ti-signature fs-8"></i></span>';
        }
        return '<a class="pointer text-primary d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px" href="/departamento-operativo/recursos-humanos/formatos/firmar/' + Number(row.id_formulario) + '" title="Firmar"><i class="ti ti-signature fs-8"></i></a>';
    };

    let table;
    table = $(tableElement).DataTable({
        processing: true,
        ajax: {
            url: '/departamento-operativo/recursos-humanos/vacaciones/data/' + year,
            type: 'GET',
            dataSrc: function (json) {
                if (!json || json.success !== true) return [];
                const visible = stationColumnVisible();
                table.column(1).visible(visible, false);
                table.columns.adjust();
                return Array.isArray(json.data) ? json.data : [];
            },
            error: function (xhr) {
                console.error('No se pudo cargar el listado de Vacaciones.', xhr);
            }
        },
        columns: [
            { title: '#', data: null, className: 'align-middle text-center', orderable: false, searchable: false, width: '40px', render: (_d, _t, _r, meta) => meta.row + meta.settings._iDisplayStart + 1 },
            { title: 'Fecha ingreso', data: 'fecha_ingreso_formateada', className: 'align-middle text-center text-nowrap', render: (value) => escapeHtml(value || 'S/I') },
            { title: 'Estación/Departamento', data: 'nombre_localidad', className: 'align-middle text-start text-center text-nowrap', visible: stationColumnVisible(), render: (value) => escapeHtml(value || 'S/I') },
            { title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-nowrap', render: (value) => escapeHtml(value || '') },
            { title: 'Puesto', data: 'nombre_puesto', className: 'align-middle text-center text-nowrap', render: (value) => escapeHtml(value || 'S/I') },
            { title: 'Mes', data: 'mes_ingreso', className: 'align-middle text-center text-nowrap', render: (value) => value ? new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(new Date(2020, Number(value) - 1, 1)) + ' ' + year : 'S/I' },
            { title: 'Prima vacacional', data: 'prima_vacacional', className: 'align-middle text-center', orderable: false, render: (value) => value ? '<a class="pointer text-primary" href="/download?tipo=recibos-nomina-firma&file=' + encodeURIComponent(value) + '" title="Descargar recibo firmado"><i class="ti ti-file-type-pdf fs-5"></i></a>' : '<i class="ti ti-file-off text-muted fs-5" title="Sin documento firmado"></i>' },
            { title: 'Días', data: 'num_dias', className: 'align-middle text-center', render: (value) => Number(value || 0).toLocaleString('es-MX') },
            { title: 'Fecha inicio', data: 'fecha_inicio', className: 'align-middle text-center text-nowrap', render: (value) => escapeHtml(value || 'S/I') },
            { title: 'Fecha regreso', data: 'fecha_regreso', className: 'align-middle text-center text-nowrap', render: (value) => escapeHtml(value || 'S/I') },
            { title: 'Total días año', data: 'total_dias_year', className: 'align-middle text-center text-nowrap', render: (value) => Number(value || 0).toLocaleString('es-MX') },
            { title: '<i class="ti ti-eye text-primary fs-6" title="Detalle"></i>', width: '48px', data: null, className: 'align-middle text-center', orderable: false, searchable: false, render: (_d, _t, row) => detailButton(row) },
            { title: '<i class="ti ti-signature text-primary fs-8" title="Firma"></i>', width: '48px', data: null, className: 'align-middle text-center', orderable: false, searchable: false, render: (_d, _t, row) => signatureButton(row) },
            { title: '<i class="ti ti-message fs-8" title="Comentarios"></i>', data: null, width: '48px', className: 'align-middle text-center', orderable: false, searchable: false, render: (_d, _t, row) => '<a href="#" class="pointer text-body btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center js-vacaciones-comentarios" style="width:30px;height:30px" data-id-personal="' + Number(row.id_personal) + '" title="Comentarios"><i class="ti ti-message text-dark fs-8"></i>' + (Number(row.num_comentarios) > 0 ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + Number(row.num_comentarios) + '</span>' : '') + '</a>' }
        ],
        order: [[3, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false,
        language: {
            processing: 'Cargando...',
            lengthMenu: 'Mostrar _MENU_ registros',
            search: 'Buscar:',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            zeroRecords: 'No se encontraron coincidencias',
            emptyTable: 'No se encontró información',
            paginate: { previous: 'Anterior', next: 'Siguiente' }
        },
        drawCallback: syncNewButton
    });

    const refreshPending = () => fetch('/departamento-operativo/recursos-humanos/vacaciones/pendientes/' + year, { credentials: 'same-origin' })
        .then((response) => response.json())
        .then((counts) => {
            const selected = selector && selector.value.match(/^(?:estacion|depto)_(\d+)$/);
            const activeCount = selected ? Number(counts['localidad_' + selected[1]] || 0) : Number(counts.total || 0);
            if (pendingBadge) pendingBadge.textContent = activeCount.toLocaleString('es-MX');
            if (selector) {
                Array.from(selector.options).forEach((option) => {
                    if (!option.dataset.baseLabel) option.dataset.baseLabel = option.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                    let count = null;
                    const match = option.value.match(/^(?:estacion|depto)_(\d+)$/);
                    if (match) count = Number(counts['localidad_' + match[1]] || 0);
                    else if (option.value === '') count = Number(counts.total || 0);
                    if (count !== null) option.textContent = option.dataset.baseLabel + (count > 0 ? ' (' + count + ')' : '');
                });
            }
        })
        .catch((error) => console.error('No se pudo actualizar el contador de Vacaciones.', error));

    const detailField = (label, value, col = 'col-12 col-md-6') => '<div class="' + col + ' mb-3"><label class="form-label mb-1">' + escapeHtml(label) + ':</label><div class="text-break">' + escapeHtml(value || 'S/I') + '</div></div>';

    const showDetail = async (id) => {
        detailFromHistory = historyModalElement.classList.contains('show');
        returnToHistory = false;
        if (detailFromHistory) {
            const historyClosed = new Promise((resolve) => historyModalElement.addEventListener('hidden.bs.modal', resolve, { once: true }));
            historyModal.hide();
            await historyClosed;
        }
        detailContent.innerHTML = '';
        detailLoading.classList.remove('d-none');
        detailModal.show();
        try {
            const response = await axios.get('/departamento-operativo/recursos-humanos/vacaciones/detalle', { params: { id } });
            const d = response.data.detalle;
            detailContent.innerHTML = [
                detailField('Nombre completo', d.nombre_completo),
                detailField('Puesto', d.nombre_puesto),
                detailField('Estación / Departamento', d.nombre_localidad),
                detailField('Fecha de solicitud', d.fecha_solicitud),
                detailField('Fecha de ingreso', d.fecha_ingreso),
                detailField('Días solicitados', Number(d.num_dias).toLocaleString('es-MX')),
                detailField('Fecha de inicio', d.fecha_inicio),
                detailField('Fecha de término', d.fecha_termino),
                detailField('Fecha de regreso', d.fecha_regreso),
                detailField('Estatus', d.status_label),
                detailField('Observaciones', d.observaciones, 'col-12')
            ].join('');
        } catch (error) {
            detailContent.innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">' + escapeHtml(error.response?.data?.message || 'No se pudo cargar el detalle.') + '</div></div>';
        } finally {
            detailLoading.classList.add('d-none');
        }
    };

    const openHistory = async (idPersonal, nombre) => {
        activeHistory = { idPersonal, nombre };
        historyTitle.textContent = 'Historial de vacaciones · ' + (nombre || 'Personal') + ' · ' + year;
        historyBody.innerHTML = '';
        historyLoading.classList.remove('d-none');
        historyModal.show();
        try {
            const response = await axios.get('/departamento-operativo/recursos-humanos/vacaciones/historial', {
                params: { id_personal: idPersonal, year }
            });
            const records = response.data.historial?.solicitudes || [];
            if (!records.length) {
                historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No se encontró información</td></tr>';
                return;
            }
            historyBody.innerHTML = records.map((record) => {
                const canDelete = root.dataset.puedeEliminar === 'true' && Number(record.status) < 3;
                return '<tr>'
                    + '<td class="text-center align-middle">' + escapeHtml(record.fecha_solicitud || 'S/I') + '</td>'
                    + '<td class="text-center align-middle">' + Number(record.num_dias || 0).toLocaleString('es-MX') + '</td>'
                    + '<td class="text-center align-middle">' + escapeHtml(record.fecha_inicio || 'S/I') + '</td>'
                    + '<td class="text-center align-middle">' + escapeHtml(record.fecha_termino || 'S/I') + '</td>'
                    + '<td class="text-center align-middle">' + escapeHtml(record.fecha_regreso || 'S/I') + '</td>'
                    + '<td class="text-center"><button type="button" class="btn p-0 border-0 bg-transparent text-primary d-inline-flex align-items-center justify-content-center js-vacaciones-detalle" style="width:30px;height:30px" data-id="' + Number(record.id) + '" title="Detalle"><i class="ti ti-eye fs-6"></i></button></td>'
                    + '<td class="text-center"><button type="button" class="btn p-0 border-0 bg-transparent ' + (canDelete ? 'text-danger' : 'text-muted opacity-50') + ' d-inline-flex align-items-center justify-content-center js-vacaciones-eliminar" style="width:30px;height:30px" data-id="' + Number(record.id) + '" data-name="Solicitud de vacaciones de ' + escapeHtml(nombre || 'Personal') + '" title="' + (canDelete ? 'Eliminar' : 'No disponible para eliminar') + '"' + (canDelete ? '' : ' disabled') + '><i class="ti ti-trash fs-6"></i></button></td></tr>';
            }).join('');
        } catch (error) {
            historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(error.response?.data?.message || 'No se pudo cargar el historial.') + '</td></tr>';
        } finally {
            historyLoading.classList.add('d-none');
        }
    };

    const detailCard = (label, value, col, icon) => '<div class="' + col + '"><div class="vacaciones-chat-detail border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px"><i class="ti ' + icon + ' fs-5"></i></span><div class="flex-grow-1 overflow-hidden"><div class="form-label small mb-0">' + escapeHtml(label) + ':</div><div class="text-break">' + escapeHtml(value || 'S/I') + '</div></div></div></div>';

    const monthLabel = (month) => Number(month) > 0
        ? new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(new Date(2020, Number(month) - 1, 1)) + ' ' + year
        : 'S/I';

    const renderComments = (messages) => {
        if (!messages.length) {
            commentList.innerHTML = '<div class="h-100 d-flex align-items-center justify-content-center text-muted"><small>No hay comentarios todavía.</small></div>';
            return;
        }
        const ownId = Number(root.dataset.idUsuario);
        commentList.innerHTML = messages.map((message) => {
            const own = Number(message.id_usuario) === ownId;
            return '<div class="d-flex ' + (own ? 'justify-content-end' : 'justify-content-start') + ' mb-2"><div class="' + (own ? 'bg-primary text-white' : 'bg-body-tertiary text-body') + ' rounded-3 px-3 py-2" style="max-width:88%"><div class="small fw-semibold mb-1">' + escapeHtml(message.nombre_usuario) + '</div><div class="text-break" style="white-space:pre-wrap">' + escapeHtml(message.comentario) + '</div><div class="text-end small opacity-75 mt-1">' + escapeHtml(message.fecha_hora) + '</div></div></div>';
        }).join('');
        commentList.scrollTop = commentList.scrollHeight;
    };

    const openComments = async (idPersonal) => {
        activeComment = { idPersonal, year };
        commentList.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Cargando comentarios...</div>';
        commentDetail.innerHTML = '';
        document.getElementById('vacaciones-comentarios-subtitle').textContent = 'Comentarios de vacaciones · ' + year;
        commentCanvas.show();
        try {
            const response = await axios.get('/departamento-operativo/recursos-humanos/vacaciones/comentarios', { params: { id_personal: idPersonal, year } });
            const detail = response.data.detalle;
            if (detail) {
                commentDetail.innerHTML = '<div class="row g-2">' +
                    detailCard('ID', detail.id_personal, 'col-4', 'ti-hash') +
                    detailCard('Nombre completo', detail.nombre_completo, 'col-8', 'ti-user') +
                    detailCard('Puesto', detail.nombre_puesto, 'col-6', 'ti-briefcase') +
                    detailCard('Estación / Departamento', detail.nombre_localidad, 'col-6', 'ti-map-pin') +
                    detailCard('Mes', monthLabel(detail.mes_ingreso), 'col-6', 'ti-calendar-event') +
                    detailCard('Días solicitados', Number(detail.num_dias || 0).toLocaleString('es-MX'), 'col-6', 'ti-calendar-event') +
                    '</div>';
            }
            renderComments(response.data.comentarios || []);
        } catch (error) {
            commentList.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.response?.data?.message || 'No se pudieron cargar los comentarios.') + '</div>';
        }
    };

    const deleteAction = async (button) => {
        if (!window.Alpine || !Alpine.$data) return;
        const row = button.closest('tr');
        const data = Alpine.$data(root);
        const result = await data.deleteAction({
            url: '/departamento-operativo/recursos-humanos/vacaciones/eliminar',
            id: Number(button.dataset.id),
            name: button.dataset.name || 'Solicitud de vacaciones',
            table: '#table-vacaciones'
        });
        if (result?.success) {
            row?.remove();
            if (historyBody && !historyBody.querySelector('tr')) {
                historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No se encontró información</td></tr>';
            }
        }
        refreshPending();
    };

    tableElement.addEventListener('click', (event) => {
        const historyButtonElement = event.target.closest('.js-vacaciones-historial');
        if (historyButtonElement) {
            event.preventDefault();
            return openHistory(Number(historyButtonElement.dataset.idPersonal), historyButtonElement.dataset.nombre || 'Personal');
        }
        const commentsButton = event.target.closest('.js-vacaciones-comentarios');
        if (commentsButton) {
            event.preventDefault();
            return openComments(Number(commentsButton.dataset.idPersonal));
        }
    });

    historyBody?.addEventListener('click', (event) => {
        const detailButtonElement = event.target.closest('.js-vacaciones-detalle');
        if (detailButtonElement) return showDetail(Number(detailButtonElement.dataset.id));
        const deleteButton = event.target.closest('.js-vacaciones-eliminar');
        if (deleteButton && !deleteButton.disabled) return deleteAction(deleteButton);
    });

    document.querySelectorAll('.js-vacaciones-detalle-close').forEach((button) => {
        button.addEventListener('click', () => { returnToHistory = false; });
    });
    document.querySelector('.js-vacaciones-detalle-back')?.addEventListener('click', () => {
        returnToHistory = detailFromHistory && !!activeHistory;
        detailModal.hide();
    });

    document.getElementById('modal-vacaciones-detalle')?.addEventListener('hidden.bs.modal', () => {
        if (returnToHistory && activeHistory) historyModal.show();
        returnToHistory = false;
        detailFromHistory = false;
    });

    newButton?.addEventListener('click', () => {
        const idLocalidad = getContextId();
        if (idLocalidad > 0 && root.dataset.puedeCrear === 'true') {
            window.location.href = '/departamento-operativo/recursos-humanos/vacaciones/nuevo/' + idLocalidad;
        }
    });

    selector?.addEventListener('change', syncNewButton);

    commentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending || !activeComment || !commentInput.value.trim()) return;
        sending = true;
        sendingIndicator.classList.remove('d-none');
        try {
            const response = await axios.post('/departamento-operativo/recursos-humanos/vacaciones/comentarios/guardar', {
                id_personal: activeComment.idPersonal,
                year: activeComment.year,
                comentario: commentInput.value.trim()
            });
            if (!response.data.success) throw new Error(response.data.message || 'No se pudo guardar el comentario.');
            commentInput.value = '';
            await openComments(activeComment.idPersonal);
            table.ajax.reload(null, false);
        } catch (error) {
            if (window.Notify) Notify.error(error.response?.data?.message || error.message || 'No se pudo guardar el comentario.');
        } finally {
            sending = false;
            sendingIndicator.classList.add('d-none');
        }
    });

    commentInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            commentForm.requestSubmit();
        }
    });

    document.getElementById('vacaciones-comentarios')?.addEventListener('hidden.bs.offcanvas', () => {
        activeComment = null;
        commentInput.value = '';
        sendingIndicator.classList.add('d-none');
        sending = false;
    });

    if (window.ModuleStationSelector && selector) {
        ModuleStationSelector.init('vacaciones', {
            customReload: () => {
                syncNewButton();
                table.column(1).visible(stationColumnVisible(), false);
                table.ajax.reload(null, false);
                refreshPending();
            }
        });
    }

    syncNewButton();
    refreshPending();
})();
