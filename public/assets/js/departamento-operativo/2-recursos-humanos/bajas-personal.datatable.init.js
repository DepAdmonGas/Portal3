document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('bajas-personal');
    const tableElement = document.getElementById('table-bajas-personal');
    if (!root || !tableElement || !window.$ || !$.fn.DataTable) return;

    const puedeEditar = root.dataset.puedeEditar === 'true';
    const selectorId = 'module-station-selector-bajas-personal';
    const pendingCount = document.getElementById('bajas-pendientes-count');
    const form = document.getElementById('form-editar-proceso-baja');
    const modalElement = document.getElementById('modalEditarProcesoBaja');
    const commentsPanel = document.getElementById('offcanvasComentariosBajaPersonal');
    const commentsList = document.getElementById('baja-personal-comentarios-lista');
    const commentsInput = document.getElementById('baja-personal-comentario-input');
    const commentsSendButton = document.getElementById('baja-personal-comentario-enviar');
    const commentsSending = document.getElementById('baja-personal-comentario-enviando');
    let activeCommentRow = null;
    let sendingComment = false;

    const isAllSelected = () => {
        const selector = document.getElementById(selectorId);
        return Boolean(selector && selector.value === '');
    };
    const updateStationColumn = (table) => {
        table.column(1).visible(isAllSelected(), false);
        table.columns.adjust().draw(false);
    };
    const text = (value) => $('<div>').text(value == null ? '' : String(value)).html();
    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value == null || value === '' ? 'S/I' : String(value);
    };
    const renderComments = (comments) => {
        if (!commentsList) return;
        commentsList.replaceChildren();
        if (!comments.length) {
            const empty = document.createElement('div');
            empty.className = 'd-flex flex-column align-items-center justify-content-center text-center';
            empty.style.minHeight = '380px';
            empty.innerHTML = '<i class="ti ti-message-off text-muted mb-2" style="font-size:55px"></i><p class="text-muted mb-0 fs-5">Sin comentarios</p>';
            commentsList.appendChild(empty);
            return;
        }
        comments.slice().sort((a, b) => Number(a.id || 0) - Number(b.id || 0)).forEach((comment) => {
            const row = document.createElement('div');
            row.className = 'd-flex mb-3 ' + (comment.esPropio ? 'justify-content-end' : 'justify-content-start');
            const body = document.createElement('div');
            body.className = comment.esPropio ? 'd-flex flex-column align-items-end' : 'd-flex gap-3 align-items-start';
            if (!comment.esPropio) {
                const avatar = document.createElement('div');
                avatar.className = 'flex-shrink-0 rounded-circle bg-dark d-flex align-items-center justify-content-center text-white';
                avatar.style.cssText = 'width:42px;height:42px';
                avatar.innerHTML = '<i class="ti ti-user fs-5"></i>';
                body.appendChild(avatar);
            }
            const content = document.createElement('div');
            if (!comment.esPropio) {
                const author = document.createElement('div');
                author.className = 'small mb-1';
                author.textContent = comment.usuario_nombre || 'Usuario';
                content.appendChild(author);
            }
            const date = document.createElement('div');
            date.className = 'fs-3 text-muted mb-1' + (comment.esPropio ? ' text-end' : '');
            date.textContent = comment.fecha_hora || '';
            content.appendChild(date);
            const bubble = document.createElement('div');
            bubble.className = 'p-3 rounded-3 text-white mt-2 ' + (comment.esPropio ? 'bg-primary' : 'text-bg-success');
            bubble.style.maxWidth = '420px';
            bubble.textContent = comment.comentario || '';
            content.appendChild(bubble);
            body.appendChild(content);
            row.appendChild(body);
            commentsList.appendChild(row);
        });
        commentsList.scrollTop = commentsList.scrollHeight;
    };
    const loadComments = async () => {
        if (!activeCommentRow) return;
        const response = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-comentarios-baja?id_baja=' + encodeURIComponent(activeCommentRow.id));
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible cargar los comentarios.');
        renderComments(Array.isArray(result.data) ? result.data : []);
        activeCommentRow.num_comentarios = Array.isArray(result.data) ? result.data.length : 0;
        table.rows().every(function () {
            const rowData = this.data();
            if (Number(rowData.id) !== Number(activeCommentRow.id)) return;
            rowData.num_comentarios = activeCommentRow.num_comentarios;
            this.data(rowData).invalidate();
        });
        table.draw(false);
    };
    const openComments = async (row) => {
        activeCommentRow = row;
        setText('baja-personal-comentario-id', row.id);
        setText('baja-personal-comentario-id-card', row.id);
        setText('baja-personal-comentario-fecha', row.fecha_baja);
        setText('baja-personal-comentario-nombre', row.nombre_completo);
        setText('baja-personal-comentario-motivo', row.motivo);
        setText('baja-personal-comentario-detalle', row.detalle);
        setText('baja-personal-comentario-proceso', row.proceso);
        setText('baja-personal-comentario-estatus', row.estado_label);
        if (commentsInput) commentsInput.value = '';
        if (commentsSendButton) commentsSendButton.disabled = true;
        renderComments([]);
        bootstrap.Offcanvas.getOrCreateInstance(commentsPanel).show();
        try {
            await loadComments();
        } catch (error) {
            if (window.Notify) Notify.error(error.message || 'No fue posible cargar los comentarios.');
        }
    };
    const statusBadge = (row) => {
        if (Number(row.estado_proceso) === 2) return '<span class="badge bg-success">Finalizado</span>';
        if (Number(row.estado_proceso) === 1) return '<span class="badge bg-warning text-dark">En Proceso</span>';
        return '<span class="badge bg-danger">Pendiente</span>';
    };

    const table = $(tableElement).DataTable({
        processing: true,
        ajax: {
            url: '/departamento-operativo/recursos-humanos/bajas-personal/get-data',
            dataSrc: (json) => {
                const rows = Array.isArray(json.data) ? json.data : [];
                const pending = rows.filter((row) => Number(row.estado_proceso) !== 2).length;
                if (pendingCount) pendingCount.textContent = String(pending);
                return rows;
            },
            error: () => {
                if (window.Notify) Notify.error('No fue posible cargar las bajas de personal.');
            }
        },
        columns: [
            { data: null, className: 'text-center', render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
            { data: 'nombre_estacion', className: 'bajas-col-estacion' },
            { data: 'nombre_completo', render: (value) => text(value) },
            { data: 'puesto', className: 'text-center', render: (value) => text(value) },
            { data: 'fecha_baja', className: 'text-center' },
            { data: 'motivo', render: (value) => text(value) },
            { data: 'detalle', render: (value) => text(value || 'S/I') },
            { data: 'proceso', render: (value) => text(value || 'S/I') },
            { data: null, className: 'text-center', render: (value, type, row) => statusBadge(row) },
            { data: null, className: 'text-center position-relative', orderable: false, render: (value, type, row) => {
                const count = Number(row.num_comentarios || 0);
                return '<a class="btn-comentarios pointer btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center js-comentarios-baja" data-id="' + Number(row.id) + '" title="Comentarios" href="javascript:void(0)"><i class="ti ti-message fs-7"></i>'
                    + (count > 0 ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>' : '') + '</a>';
            }},
            { data: null, className: 'text-center', orderable: false, render: (value, type, row) => {
                const detail = '<a class="dropdown-item" href="/departamento-operativo/recursos-humanos/control-documentos-personal/detalle-baja/' + Number(row.id) + '"><i class="ti ti-eye me-1"></i>Detalle</a>';
                const edit = puedeEditar && Number(row.estado_proceso) !== 2
                    ? '<button type="button" class="dropdown-item js-editar-proceso" data-id="' + Number(row.id) + '" data-proceso="' + text(row.proceso) + '" data-estado="' + Number(row.estado_proceso) + '"><i class="ti ti-edit me-1"></i>Editar proceso</button>'
                    : '';
                return '<div class="dropdown dropstart d-inline-block"><a class="pointer" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu">' + detail + edit + '</div></div>';
            }}
        ],
        columnDefs: [{ targets: 1, visible: isAllSelected() }],
        order: [[4, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: 'No se encontró información',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            lengthMenu: 'Mostrar _MENU_ registros',
            search: 'Buscar:',
            paginate: { previous: 'Anterior', next: 'Siguiente' },
            processing: 'Cargando...'
        }
    });

    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init('bajas-personal', {
            customReload: () => {
                updateStationColumn(table);
                table.ajax.reload(null, true);
            }
        });
    }

    $(tableElement).on('click', '.js-comentarios-baja', function (event) {
        event.preventDefault();
        const row = table.row($(this).closest('tr')).data();
        if (row) openComments(row);
    });

    const updateCommentButton = () => {
        if (commentsSendButton && commentsInput) commentsSendButton.disabled = sendingComment || !commentsInput.value.trim();
        if (commentsInput) commentsInput.disabled = sendingComment;
    };
    commentsInput?.addEventListener('input', updateCommentButton);
    commentsInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            commentsSendButton?.click();
        }
    });
    commentsSendButton?.addEventListener('click', async () => {
        if (sendingComment || !activeCommentRow || !commentsInput?.value.trim()) return;
        sendingComment = true;
        updateCommentButton();
        if (commentsSending) commentsSending.classList.remove('d-none');
        const comment = commentsInput.value.trim();
        try {
            const response = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/add-comentario-baja', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_baja: activeCommentRow.id, comentario: comment })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible enviar el comentario.');
            commentsInput.value = '';
            await loadComments();
        } catch (error) {
            if (window.Notify) Notify.error(error.message || 'No fue posible enviar el comentario.');
        } finally {
            sendingComment = false;
            if (commentsSending) commentsSending.classList.add('d-none');
            updateCommentButton();
        }
    });

    $(tableElement).on('click', '.js-editar-proceso', function () {
        form.elements.id_baja.value = this.dataset.id;
        form.elements.proceso.value = this.dataset.proceso || '';
        form.elements.estado_proceso.value = this.dataset.estado || '0';
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch('/departamento-operativo/recursos-humanos/bajas-personal/update', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo actualizar el proceso.');
            bootstrap.Modal.getInstance(modalElement)?.hide();
            if (window.Notify) Notify.success(result.message || 'Proceso actualizado.');
            table.ajax.reload(null, false);
        } catch (error) {
            if (window.Notify) Notify.error(error.message || 'No se pudo actualizar el proceso.');
        } finally {
            button.disabled = false;
        }
    });
});
