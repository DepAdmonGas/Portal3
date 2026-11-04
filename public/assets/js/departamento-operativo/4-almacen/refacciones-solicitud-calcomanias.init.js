document.addEventListener('DOMContentLoaded', () => {
    const toast = (type, message) => {
        if (window.Notify && typeof window.Notify[type] === 'function') {
            window.Notify[type](message);
            return;
        }
        if (window.alertify && typeof window.alertify[type] === 'function') {
            window.alertify[type](message);
            return;
        }
        return Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    };
    const post = async (url, data = {}) => {
        const response = await fetch(url, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible completar la solicitud.');
        return result;
    };

    const list = document.getElementById('refacciones-solicitud-calcomanias');
    if (list && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable('#tabla-solicitudes-calcomanias')) {
        const table = document.getElementById('tabla-solicitudes-calcomanias');
        const allLocations = table.querySelector('thead th:nth-child(4)')?.textContent.trim() === 'Estación / departamento';
        const dataTable = jQuery(table).DataTable({
            order: [[allLocations ? 4 : 3, 'desc']], pageLength: 10,
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columnDefs: [{ targets: '_all', className: 'text-center' }, { targets: [1], className: 'text-start' }, { targets: [-1], orderable: false, searchable: false }]
        });
        const modalNode = document.getElementById('modal-detalle-solicitud-calcomanias');
        const detailModal = modalNode && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalNode) : null;
        const setText = (selector, value) => { const node = modalNode?.querySelector(selector); if (node) node.textContent = value || 'Sin información'; };
        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('es-MX', { dateStyle: 'long', timeStyle: 'short' }).format(date);
        };
        const renderSignature = (container, signature, name, date) => {
            if (!container) return;
            const card = container.closest('.card');
            const footer = card?.querySelector('.card-footer');
            const headerIcon = card?.querySelector('[data-modal-firma-icon]');
            container.replaceChildren();
            footer?.replaceChildren();
            const hasSignature = Boolean(signature);
            const isImageSignature = hasSignature && /^data:image\/(png|jpeg);base64,/.test(signature);

            if (headerIcon) {
                headerIcon.className = `ti ${hasSignature ? 'ti-user-check' : 'ti-clock-hour-4'} fs-5`;
            }

            if (hasSignature) {
                if (isImageSignature) {
                    const image = document.createElement('img');
                    image.src = signature;
                    image.alt = 'Firma digital';
                    image.className = 'img-fluid';
                    image.style.maxHeight = '90px';
                    image.style.objectFit = 'contain';
                    container.append(image);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'ti ti-signature text-primary mb-3';
                    icon.style.fontSize = '70px';
                    container.append(icon);
                    const label = document.createElement('small');
                    label.className = 'text-dark';
                    label.textContent = signature;
                    if (date) {
                        const timestamp = document.createElement('small');
                        timestamp.className = 'text-dark fw-semibold mb-2';
                        timestamp.textContent = `Fecha: ${formatDate(date)}`;
                        container.append(timestamp);
                    }
                    container.append(label);
                }

                if (footer) {
                    const signer = document.createElement('h6');
                    signer.className = 'mb-0 fw-semibold text-truncate';
                    signer.textContent = name || 'Sin nombre registrado';
                    footer.append(signer);
                }
                return;
            }

            const emptyIcon = document.createElement('i');
            emptyIcon.className = 'ti ti-signature-off text-gray mb-3';
            emptyIcon.style.fontSize = '100px';
            container.append(emptyIcon);
            const emptyText = document.createElement('h6');
            emptyText.className = 'text-muted mb-0';
            emptyText.textContent = 'Sin firma registrada';
            container.append(emptyText);
            if (footer) {
                const pending = document.createElement('small');
                pending.className = 'text-muted';
                pending.textContent = 'Pendiente de firma electrónica';
                footer.append(pending);
            }
        };
        list.addEventListener('click', async (event) => {
            const detailButton = event.target.closest('.js-solicitud-detalle');
            if (detailButton) {
                event.preventDefault();
                try {
                    const response = await fetch(`${list.dataset.baseUrl}/solicitud-calcomanias/detalle?id=${encodeURIComponent(detailButton.dataset.id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const json = await response.json();
                    if (!response.ok || !json.success || !json.solicitud) throw new Error(json.message || 'No fue posible cargar el detalle.');
                    const item = json.solicitud;
                    const statusText = ['Pendiente', 'En proceso', 'Finalizado'][Number(item.estado)] || 'Desconocido';
                    const statusClass = ['bg-danger text-white', 'bg-warning text-white', 'bg-success text-white'][Number(item.estado)] || 'bg-secondary text-white';
                    setText('[data-modal-folio]', `#00${item.id}`);
                    setText('[data-modal-folio-value]', `#00${item.id}`);
                    setText('[data-modal-fecha]', formatDate(item.fecha_hora));
                    setText('[data-modal-personal]', item.nombre_personal);
                    setText('[data-modal-puesto]', item.puesto || '—');
                    setText('[data-modal-ubicacion]', item.nombre_ubicacion || '—');
                    const status = modalNode.querySelector('[data-modal-estado]');
                    status.replaceChildren(); const badge = document.createElement('span'); badge.className = `badge ${statusClass}`; badge.textContent = statusText; status.append(badge);
                    const itemsBody = modalNode.querySelector('[data-modal-items]'); itemsBody.replaceChildren();
                    (item.detalle || []).forEach((line, index) => {
                        const row = document.createElement('tr');
                        [String(index + 1), line.nombre_calcomania || '', String(Number(line.cantidad) || 0)].forEach((value, cellIndex) => {
                            const cell = document.createElement('td'); cell.textContent = value; if (cellIndex === 1) cell.className = 'text-start'; row.append(cell);
                        });
                        itemsBody.append(row);
                    });
                    if (!item.detalle?.length) { const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = 3; cell.className = 'text-muted'; cell.textContent = 'No se encontró información'; row.append(cell); itemsBody.append(row); }
                    setText('[data-modal-observaciones]', item.observaciones || 'Sin información');
                    renderSignature(modalNode.querySelector('[data-modal-firma-encargado]'), item.firma_encargado, item.nombre_personal, item.firmado_encargado_en);
                    renderSignature(modalNode.querySelector('[data-modal-firma-autorizacion]'), item.firma_autorizacion_descripcion || item.firma_autorizacion, item.nombre_usuario_autorizacion, item.firmado_autorizacion_en);
                    detailModal?.show();
                } catch (error) { toast('error', error.message); }
                return;
            }
            const pdfButton = event.target.closest('.js-solicitud-pdf');
            if (pdfButton) {
                event.preventDefault();
                if (pdfButton.classList.contains('disabled')) return;
                window.open(`${list.dataset.baseUrl}/solicitud-calcomanias/pdf/${encodeURIComponent(pdfButton.dataset.id)}`, '_blank', 'noopener');
                return;
            }
            const editButton = event.target.closest('.js-solicitud-editar');
            if (editButton?.classList.contains('disabled')) { event.preventDefault(); return; }
            const deleteButton = event.target.closest('.js-solicitud-eliminar');
            if (deleteButton) {
                event.preventDefault();
                if (deleteButton.classList.contains('disabled')) return;
                const actions = window.Alpine?.$data(list);
                if (!actions?.deleteAction) return toast('error', 'No está disponible la confirmación global para eliminar.');
                const result = await actions.deleteAction({
                    url: `${list.dataset.baseUrl}/solicitud-calcomanias/eliminar`,
                    id: Number(deleteButton.dataset.id), name: deleteButton.dataset.name || 'solicitud', table: null,
                });
                if (result?.success) {
                    const row = deleteButton.closest('tr[data-request-row]');
                    dataTable.row(row).remove().draw(false);
                }
            }
        });
    }
    list?.querySelector('[data-crear-solicitud]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const result = await post(`${list.dataset.baseUrl}/solicitud-calcomanias/guardar`);
            window.location.assign(result.url);
        } catch (error) {
            toast('error', error.message);
            button.disabled = false;
        }
    });

    const detail = document.getElementById('solicitud-calcomanias-detalle');
    const signaturePage = document.getElementById('solicitud-calcomanias-firma');
    const root = detail || signaturePage;
    if (!root) return;
    const baseUrl = root.dataset.baseUrl;
    const requestId = Number(root.dataset.requestId || 0);
    const editable = root.dataset.editable === '1';
    const modalNode = root.querySelector('#modal-calcomania-solicitud');
    const modal = modalNode && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalNode) : null;
    const tbody = root.querySelector('[data-calcomanias-body]');
    const calcomaniaSelect = root.querySelector('[data-select-calcomania]');
    const calcomaniaQuantity = root.querySelector('[data-calcomania-cantidad]');

    calcomaniaSelect?.addEventListener('change', () => calcomaniaSelect.classList.remove('is-invalid'));
    calcomaniaQuantity?.addEventListener('input', () => calcomaniaQuantity.classList.remove('is-invalid'));

    root.querySelector('[data-open-calcomania]')?.addEventListener('click', () => modal?.show());
    root.querySelector('[data-save-calcomania]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const id = Number(calcomaniaSelect?.value || 0);
        const amount = Number(calcomaniaQuantity?.value || 0);
        calcomaniaSelect?.classList.toggle('is-invalid', !id);
        calcomaniaQuantity?.classList.toggle('is-invalid', !Number.isInteger(amount) || amount < 1);
        if (!id || !Number.isInteger(amount) || amount < 1) {
            toast('error', 'Completa los campos obligatorios y revisa los valores señalados.');
            if (!id) calcomaniaSelect?.focus();
            else calcomaniaQuantity?.focus();
            return;
        }
        button.disabled = true;
        try {
            const result = await post(`${baseUrl}/solicitud-calcomanias/agregar-calcomania`, {
                id_solicitud: requestId, id_calcomania: id, cantidad: amount
            });
            const item = result.solicitud?.detalle?.find((row) => Number(row.id_calcomania) === id);
            if (!item) throw new Error('La calcomanía se guardó, pero no fue posible actualizar la tabla.');
            tbody.querySelector('[data-empty-row]')?.remove();
            let row = [...tbody.querySelectorAll('tr[data-detail-id]')].find((tr) => Number(tr.dataset.catalogId) === id);
            if (row) {
                row.cells[2].textContent = String(item.cantidad);
            } else {
                row = document.createElement('tr');
                row.dataset.detailId = String(item.id);
                row.dataset.catalogId = String(id);
                row.innerHTML = `<td></td><td class="text-start"></td><td></td><td><button type="button" class="btn btn-link p-0 text-decoration-none" data-remove-calcomania="${Number(item.id)}" aria-label="Eliminar calcomanía" title="Eliminar"><i class="ti ti-trash fs-6 text-danger"></i></button></td>`;
                row.cells[1].textContent = item.nombre_calcomania || '';
                row.cells[2].textContent = String(item.cantidad);
                row.querySelector('[data-remove-calcomania]')?.setAttribute('data-calcomania-name', item.nombre_calcomania || '');
                tbody.appendChild(row);
            }
            [...tbody.querySelectorAll('tr[data-detail-id]')].forEach((tr, index) => { tr.cells[0].textContent = String(index + 1); });
            if (calcomaniaSelect) calcomaniaSelect.value = '';
            if (calcomaniaQuantity) calcomaniaQuantity.value = '1';
            modal?.hide();
            toast('success', result.message || 'Calcomanía agregada.');
        } catch (error) {
            toast('error', error.message);
        } finally { button.disabled = false; }
    });

    tbody?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-calcomania]');
        if (!button) return;
        event.preventDefault();
        const actions = window.Alpine?.$data(root);
        if (!actions?.deleteAction) return toast('error', 'No está disponible la confirmación global para eliminar.');
        const row = button.closest('tr');
        const result = await actions.deleteAction({
            url: `${baseUrl}/solicitud-calcomanias/eliminar-calcomania`,
            id: Number(button.dataset.removeCalcomania),
            name: button.dataset.calcomaniaName || row?.cells[1]?.textContent?.trim() || 'calcomanía',
            table: null,
            data: { id_solicitud: requestId, id_detalle: Number(button.dataset.removeCalcomania) },
        });
        if (!result?.success) return;
        button.disabled = true;
        row?.remove();
        const rows = [...tbody.querySelectorAll('tr[data-detail-id]')];
        rows.forEach((tr, index) => { tr.cells[0].textContent = String(index + 1); });
        if (!rows.length) {
            const empty = document.createElement('tr');
            empty.dataset.emptyRow = '';
            empty.innerHTML = '<td colspan="4" class="text-center py-3">No se encontró información</td>';
            tbody.appendChild(empty);
        }
    });

    const observations = root.querySelector('[data-observaciones]');
    let lastObservations = observations?.value || '';
    observations?.addEventListener('blur', async () => {
        if (observations.value === lastObservations) return;
        observations.disabled = true;
        try {
            const result = await post(`${baseUrl}/solicitud-calcomanias/observaciones`, {
                id_solicitud: requestId, observaciones: observations.value
            });
            lastObservations = observations.value;
            toast('success', result.message || 'Observaciones guardadas.');
        } catch (error) { toast('error', error.message); }
        finally { observations.disabled = false; }
    });

    if (signaturePage) {
        const tokenInput = root.querySelector('[data-authorization-token]');
        const finishButton = root.querySelector('[data-finish-authorization]');
        const createButtons = [...root.querySelectorAll('[data-create-token]')];
        const cooldownKey = 'refacciones_solicitud_calcomanias_token_disableTime';
        let cooldownTimer = null;
        const setCreateButtonsDisabled = (disabled) => createButtons.forEach((button) => { button.disabled = disabled; });
        const startTokenCooldown = (startedAt = Date.now()) => {
            const remaining = 30000 - (Date.now() - startedAt);
            if (remaining <= 0) {
                localStorage.removeItem(cooldownKey);
                setCreateButtonsDisabled(false);
                return;
            }
            setCreateButtonsDisabled(true);
            window.clearTimeout(cooldownTimer);
            cooldownTimer = window.setTimeout(() => startTokenCooldown(startedAt), remaining);
        };
        const previousCooldown = Number(localStorage.getItem(cooldownKey) || 0);
        if (previousCooldown) startTokenCooldown(previousCooldown);
        tokenInput?.addEventListener('input', () => {
            tokenInput.value = tokenInput.value.replace(/\D/g, '').slice(0, 6);
            if (finishButton) finishButton.disabled = tokenInput.value.length !== 6;
        });
        createButtons.forEach((button) => button.addEventListener('click', async () => {
            if (button.disabled) return;
            setCreateButtonsDisabled(true);
            try {
                const via = button.dataset.createToken;
                const result = await post(`${baseUrl}/solicitud-calcomanias/crear-token-autorizacion`, { id: requestId, via });
                toast('success', result.message || `Token enviado por ${via === 'email' ? 'correo electrónico' : 'Telegram'}.`);
                const now = Date.now();
                localStorage.setItem(cooldownKey, String(now));
                startTokenCooldown(now);
                if (window.Notify) Notify.warning('Deberá esperar 30 segundos antes de generar otro token.');
            } catch (error) {
                toast('error', error.message);
                setCreateButtonsDisabled(false);
            }
        }));
        finishButton?.addEventListener('click', async () => {
            if (!tokenInput || tokenInput.value.length !== 6) return toast('error', 'Ingresa el token de seguridad de seis dígitos recibido por Telegram o correo electrónico.');
            finishButton.disabled = true;
            try {
                const result = await post(`${baseUrl}/solicitud-calcomanias/firmar-autorizacion`, { id: requestId, token: tokenInput.value });
                toast('success', result.message || 'La solicitud fue autorizada.');
                let returnUrl = `${baseUrl}/solicitud-calcomanias`;
                try {
                    const previousUrl = new URL(document.referrer);
                    if (previousUrl.origin === window.location.origin) returnUrl = previousUrl.href;
                } catch (_) {}
                window.setTimeout(() => window.location.assign(returnUrl), 700);
            } catch (error) {
                toast('error', error.message);
                finishButton.disabled = false;
            }
        });
    }

    const canvas = root.querySelector('[data-signature-canvas]');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let drew = false;
    const resize = () => {
        const rect = canvas.getBoundingClientRect();
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = Math.floor(rect.width * ratio);
        canvas.height = Math.floor(rect.height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#172b4d';
    };
    resize();
    window.addEventListener('resize', resize);
    const point = (event) => { const rect = canvas.getBoundingClientRect(); return { x: event.clientX - rect.left, y: event.clientY - rect.top }; };
    canvas.addEventListener('pointerdown', (event) => { drawing = true; drew = true; canvas.setPointerCapture(event.pointerId); const p = point(event); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('pointermove', (event) => { if (!drawing) return; const p = point(event); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => canvas.addEventListener(name, () => { drawing = false; }));
    root.querySelector('[data-clear-signature]')?.addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); drew = false; });

    const submitSignature = async (endpoint, button) => {
        if (endpoint === 'firmar-encargado' && !tbody?.querySelector('tr[data-detail-id]')) {
            toast('error', 'Agrega al menos una calcomanía antes de finalizar.');
            return;
        }
        if (!drew) { toast('error', 'Agrega la firma antes de continuar.'); return; }
        button.disabled = true;
        try {
            if (endpoint === 'firmar-encargado' && observations && observations.value !== lastObservations) {
                await post(`${baseUrl}/solicitud-calcomanias/observaciones`, {
                    id_solicitud: requestId, observaciones: observations.value
                });
                lastObservations = observations.value;
            }
            const result = await post(`${baseUrl}/solicitud-calcomanias/${endpoint}`, { id: requestId, firma: canvas.toDataURL('image/png') });
            await toast('success', result.message || 'Firma registrada.');
            let returnUrl = `${baseUrl}/solicitud-calcomanias`;
            try {
                const previousUrl = new URL(document.referrer);
                if (previousUrl.origin === window.location.origin) returnUrl = previousUrl.href;
            } catch (_) {}
            window.setTimeout(() => window.location.assign(returnUrl), 700);
        } catch (error) { toast('error', error.message); button.disabled = false; }
    };
    root.querySelector('[data-sign-requester]')?.addEventListener('click', (event) => submitSignature('firmar-encargado', event.currentTarget));
});
