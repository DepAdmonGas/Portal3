(function () {
    'use strict';

    const root = document.getElementById('vacaciones-container');
    const tableElement = document.getElementById('table-vacaciones');
    if (!root || !tableElement || !window.jQuery || !$.fn.DataTable) return;

    const year = Number(root.dataset.year) || new Date().getFullYear();
    const selector = document.getElementById('module-station-selector-vacaciones');
    const pendingBadge = document.getElementById('vacaciones-pendientes-count');
    const toolsAnchor = document.getElementById('vacaciones-tools-anchor');
    const toolsTemplate = document.getElementById('vacaciones-tools-template');
    if (toolsAnchor && toolsTemplate) toolsAnchor.appendChild(toolsTemplate.content.cloneNode(true));
    const toolsWrapper = document.getElementById('vacaciones-tools-wrapper');
    const newButton = document.getElementById('vacaciones-nuevo');
    const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-vacaciones-detalle'));
    const historyModalElement = document.getElementById('modal-vacaciones-historial');
    const historyModal = bootstrap.Modal.getOrCreateInstance(historyModalElement);
    const detailContent = document.getElementById('vacaciones-detalle-content');
    const detailLoading = document.getElementById('vacaciones-detalle-loading');
    const historyBody = document.getElementById('vacaciones-historial-body');
    const historyLoading = document.getElementById('vacaciones-historial-loading');
    const historyTitle = document.getElementById('vacaciones-historial-title');
    const commentCanvasElement = document.getElementById('vacaciones-comentarios');
    const commentCanvas = bootstrap.Offcanvas.getOrCreateInstance(commentCanvasElement);
    const commentList = document.getElementById('vacaciones-comentarios-list');
    const commentEmpty = document.getElementById('vacaciones-comentarios-empty');
    const commentItems = document.getElementById('vacaciones-comentarios-items');
    const commentDetail = document.getElementById('vacaciones-comentarios-detalle');
    const commentInput = document.getElementById('vacaciones-comentario-input');
    const commentForm = document.getElementById('vacaciones-comentario-form');
    const commentSendButton = document.getElementById('vacaciones-comentario-send');
    let activeComment = null;
    let activeHistory = null;
    let returnToHistory = false;
    let detailFromHistory = false;
    let sending = false;
    let currentComments = [];

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
        if (!newButton || !toolsWrapper) return;
        const locality = getContextId();
        const visible = root.dataset.puedeCrear === 'true' && locality > 0;
        toolsWrapper.classList.toggle('d-none', !visible);
        newButton.classList.toggle('disabled', !visible);
        newButton.setAttribute('aria-disabled', visible ? 'false' : 'true');
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
            { title: 'Prima vacacional', data: 'prima_vacacional', className: 'align-middle text-center', orderable: false, render: (value, _type, row) => row.prima_vacacional_disponible && value ? '<a class="pointer text-primary" href="/download?tipo=recibos-nomina-firma&file=' + encodeURIComponent(value) + '" title="Descargar recibo firmado"><i class="ti ti-file-type-pdf fs-6"></i></a>' : '<i class="ti ti-file-off text-muted fs-6" title="Documento no disponible"></i>' },
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

    const detailCard = (label, value, col, icon, tone = 'primary') => '<div class="' + col + '"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-' + tone + '-subtle text-' + tone + ' flex-shrink-0" style="width:34px;height:34px"><i class="ti ' + icon + ' fs-5"></i></span><div class="flex-grow-1 overflow-hidden"><div class="form-label small mb-0">' + escapeHtml(label) + ':</div><div class="text-break">' + escapeHtml(value || 'S/I') + '</div></div></div></div>';

    const monthLabel = (month) => Number(month) > 0
        ? new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(new Date(2020, Number(month) - 1, 1)) + ' ' + year
        : 'S/I';

    const renderCommentDetail = (detail) => {
        if (!detail) {
            commentDetail.innerHTML = '';
            return;
        }
        commentDetail.innerHTML = '<div class="row g-2">' +
            detailCard('ID', detail.id_personal, 'col-5', 'ti-hash') +
            detailCard('Nombre completo', detail.nombre_completo, 'col-7', 'ti-user', 'info') +
            detailCard('Puesto', detail.nombre_puesto, 'col-6', 'ti-briefcase') +
            detailCard('Estación / Departamento', detail.nombre_localidad, 'col-6', 'ti-map-pin', 'info') +
            detailCard('Mes', monthLabel(detail.mes_ingreso), 'col-6', 'ti-calendar-event') +
            detailCard('Días solicitados', Number(detail.num_dias || 0).toLocaleString('es-MX'), 'col-6', 'ti-calendar-event', 'success') +
            '</div>';
    };

    const loadComments = async (idPersonal) => {
        const params = new URLSearchParams({ id_personal: String(idPersonal), year: String(year) });
        const response = await fetch('/departamento-operativo/recursos-humanos/vacaciones/comentarios?' + params.toString(), { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || data.success !== true) throw new Error(data.message || 'No se pudieron cargar los comentarios.');
        renderCommentDetail(data.detalle);
        renderComments(data.comentarios || []);
    };

    const renderComments = (messages) => {
        currentComments = Array.isArray(messages) ? messages : [];
        if (commentEmpty) commentEmpty.classList.toggle('d-none', currentComments.length > 0 || sending);
        if (!commentItems) return;
        if (commentList) commentList.classList.toggle('d-none', sending && currentComments.length === 0);
        const ownId = Number(root.dataset.idUsuario);
        commentItems.innerHTML = (window.ordenarComentariosChat
            ? window.ordenarComentariosChat(currentComments)
            : currentComments).map((message) => {
            const own = Number(message.id_usuario) === ownId;
            const fecha = '<div class="fs-3 text-muted mb-1' + (own ? ' text-end' : '') + '">' + escapeHtml(message.fecha_hora) + '</div>';
            const bubble = '<div class="p-3 ' + (own ? 'bg-primary' : 'text-bg-success') + ' text-white rounded-3 mt-2" style="max-width:420px;white-space:pre-wrap;overflow-wrap:anywhere">' + escapeHtml(message.comentario) + '</div>';
            if (own) return '<div class="d-flex mb-3 justify-content-end"><div class="d-flex flex-column align-items-end">' + fecha + bubble + '</div></div>';
            const avatar = '<img class="rounded-circle object-fit-cover flex-shrink-0" style="width:45px;height:45px" src="' + escapeHtml(window.fotoComentario(message)) + '" alt="Foto de perfil" loading="lazy" data-profile-avatar>';
            return '<div class="d-flex mb-3 justify-content-start"><div class="d-flex gap-3 align-items-start"><div class="flex-shrink-0">' + avatar + '</div><div><h6 class="fw-semibold mb-1">' + escapeHtml(message.nombre_usuario || 'Usuario') + '</h6>' + fecha + '<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width:420px;white-space:pre-wrap;overflow-wrap:anywhere">' + escapeHtml(message.comentario) + '</div></div></div></div>';
        }).join('');
        if (commentList) commentList.scrollTop = commentList.scrollHeight;
    };

    const syncCommentSendButton = () => {
        if (!commentSendButton || !commentInput) return;
        const hasText = !!commentInput.value.trim();
        commentSendButton.disabled = sending || !hasText;
        commentSendButton.innerHTML = sending ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' : '<i class="ti ti-send fs-5"></i>';
        commentSendButton.setAttribute('aria-label', sending ? 'Enviando comentario' : 'Enviar comentario');
    };

    const setSendingState = (value) => {
        sending = value;
        const alpineData = window.Alpine && typeof window.Alpine.$data === 'function' ? window.Alpine.$data(commentCanvasElement) : null;
        if (alpineData) alpineData.guardandoComentario = value;
        syncCommentSendButton();
        renderComments(currentComments);
    };

    const openComments = async (idPersonal) => {
        activeComment = { idPersonal, year };
        commentItems.innerHTML = '';
        commentEmpty.classList.add('d-none');
        commentList.classList.remove('d-none');
        commentList.insertAdjacentHTML('afterbegin', '<div class="text-center py-4" id="vacaciones-comentarios-loading"><span class="spinner-border spinner-border-sm me-2"></span>Cargando comentarios...</div>');
        commentDetail.innerHTML = '';
        commentInput.value = '';
        syncCommentSendButton();
        document.getElementById('vacaciones-comentarios-subtitle').textContent = 'Comentarios de vacaciones · ' + year;
        commentCanvas.show();
        try {
            await loadComments(idPersonal);
        } catch (error) {
            commentItems.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message || 'No se pudieron cargar los comentarios.') + '</div>';
        } finally {
            document.getElementById('vacaciones-comentarios-loading')?.remove();
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

    newButton?.addEventListener('click', (event) => {
        event.preventDefault();
        const idLocalidad = getContextId();
        if (idLocalidad > 0 && root.dataset.puedeCrear === 'true' && !newButton.classList.contains('disabled')) {
            window.location.href = '/departamento-operativo/recursos-humanos/vacaciones/nuevo/' + idLocalidad;
        }
    });

    selector?.addEventListener('change', syncNewButton);

    commentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending || !activeComment || !commentInput.value.trim()) return;
        const idPersonal = activeComment.idPersonal;
        setSendingState(true);
        try {
            const formData = new FormData();
            formData.append('id_personal', String(idPersonal));
            formData.append('year', String(activeComment.year));
            formData.append('comentario', commentInput.value.trim());
            const response = await fetch('/departamento-operativo/recursos-humanos/vacaciones/comentarios/guardar', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const data = await response.json();
            if (!response.ok || data.success !== true) throw new Error(data.message || 'No se pudo guardar el comentario.');
            commentInput.value = '';
            await loadComments(idPersonal);
            table.ajax.reload(null, false);
        } catch (error) {
            if (window.Notify) Notify.error(error.message || 'No se pudo guardar el comentario.');
        } finally {
            setSendingState(false);
        }
    });

    commentInput?.addEventListener('input', syncCommentSendButton);

    commentInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (commentForm && !commentSendButton.disabled) commentForm.requestSubmit();
        }
    });

    document.getElementById('vacaciones-comentarios')?.addEventListener('hidden.bs.offcanvas', () => {
        activeComment = null;
        commentInput.value = '';
        syncCommentSendButton();
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
