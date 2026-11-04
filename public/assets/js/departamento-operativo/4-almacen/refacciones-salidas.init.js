(() => {
    const notify = (type, message) => {
        if (window.Notify && typeof Notify[type] === 'function') Notify[type](message);
        else if (window.alertify && typeof alertify[type] === 'function') alertify[type](message);
    };

    const table = document.getElementById('tabla-refacciones-salidas');
    if (table && window.jQuery && jQuery.fn.DataTable) {
        const dataTable = jQuery(table).DataTable({
            autoWidth: false,
            stateSave: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columnDefs: [
                { targets: 0, width: '96px', orderable: true, searchable: false, className: 'text-center', render: (data, type, _row, meta) => type === 'display' ? meta.row + meta.settings._iDisplayStart + 1 : Number(data) || 0 },
                { targets: [1, 3, 4, 5, 6, 7, 8, 9], className: 'text-center' },
                { targets: [7, 9], orderable: false, searchable: false },
            ],
        });
        dataTable.on('draw', () => {
            if (window.bootstrap?.Dropdown) table.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((button) => bootstrap.Dropdown.getOrCreateInstance(button));
        });
    }

    const salidaList = document.getElementById('refacciones-salidas');
    if (salidaList && table) {
        const detailModal = document.getElementById('modal-detalle-salida-refacciones');
        const detailModalInstance = detailModal && window.bootstrap?.Modal ? bootstrap.Modal.getOrCreateInstance(detailModal) : null;
        const returnModal = document.getElementById('modal-fecha-retorno-salida');
        const returnForm = document.getElementById('form-fecha-retorno-salida');
        const returnModalInstance = returnModal && window.bootstrap?.Modal ? bootstrap.Modal.getOrCreateInstance(returnModal) : null;
        const returnInput = returnForm?.querySelector('[data-salida-return-input]');
        const returnProvider = returnForm?.querySelector('[data-salida-return-provider]');
        const returnReference = returnForm?.querySelector('[data-salida-return-reference]');
        const returnNotes = returnForm?.querySelector('[data-salida-return-notes]');
        const formatReturnDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });

        if (window.jQuery && jQuery.fn.select2 && returnProvider) {
            jQuery(returnProvider).select2({ width: '100%', language: 'es', placeholder: 'Selecciona un proveedor', dropdownParent: jQuery(returnModal) });
        }
        [returnInput, returnProvider, returnReference].forEach((control) => control?.addEventListener('change', () => control.classList.remove('is-invalid')));
        returnReference?.addEventListener('input', () => returnReference.classList.remove('is-invalid'));

        salidaList.addEventListener('click', async (event) => {
            const detailButton = event.target.closest('[data-salida-detail]');
            if (detailButton) {
                event.preventDefault();
                if (!detailModalInstance) return notify('error', 'No está disponible el modal de detalle.');
                const fields = {
                    folio: 'folio', fecha: 'fecha', producto: 'producto', origen: 'origen', destino: 'destino',
                    cantidad: 'cantidad', retorno: 'retorno', motivo: 'motivo', notas: 'notas',
                };
                Object.entries(fields).forEach(([key, field]) => {
                    const target = detailModal.querySelector(`[data-salida-detail-${field}]`);
                    if (target) target.textContent = detailButton.dataset[key] || (key === 'notas' ? 'Sin información' : '—');
                });
                const returnState = detailModal.querySelector('[data-salida-detail-estado-retorno]');
                returnState.textContent = detailButton.dataset.estadoRetorno || '';
                returnState.className = `badge ${detailButton.dataset.estadoRetornoClass || 'bg-warning text-white'}`;
                const status = detailModal.querySelector('[data-salida-detail-estado]');
                status.textContent = detailButton.dataset.estado || '';
                status.className = `badge ${detailButton.dataset.estadoClass || 'bg-danger text-white'}`;
                ['elabora', 'recibe'].forEach((kind) => {
                    const signaturePath = detailButton.dataset[`firma${kind[0].toUpperCase()}${kind.slice(1)}`] || '';
                    const image = detailModal.querySelector(`[data-salida-detail-firma-imagen="${kind}"]`);
                    const empty = detailModal.querySelector(`[data-salida-detail-firma-vacia="${kind}"]`);
                    const name = detailModal.querySelector(`[data-salida-detail-firma-nombre="${kind}"]`);
                    if (image) {
                        image.classList.toggle('d-none', !signaturePath);
                        image.onerror = () => { image.classList.add('d-none'); if (empty) empty.classList.remove('d-none'); };
                        if (signaturePath) image.src = `/${signaturePath.replace(/^\/+/, '')}`;
                        else image.removeAttribute('src');
                    }
                    empty?.classList.toggle('d-none', Boolean(signaturePath));
                    if (name) name.textContent = detailButton.dataset[`nombre${kind[0].toUpperCase()}${kind.slice(1)}`] || '—';
                });
                detailModalInstance.show();
                return;
            }

            const deleteButton = event.target.closest('[data-salida-delete]');
            if (deleteButton && !deleteButton.disabled) {
                event.preventDefault();
                const actions = window.Alpine?.$data(salidaList);
                if (!actions?.deleteAction) return notify('error', 'No está disponible la confirmación global para eliminar.');
                const result = await actions.deleteAction({
                    url: salidaList.dataset.salidaDeleteUrl,
                    id: Number(deleteButton.dataset.id),
                    name: `la salida ${deleteButton.dataset.folio || '#' + deleteButton.dataset.id}`,
                });
                if (result?.success) jQuery(table).DataTable().row(deleteButton.closest('tr')).remove().draw(false);
                return;
            }

            const returnButton = event.target.closest('[data-salida-return]');
            if (!returnButton) return;
            event.preventDefault();
            if (returnButton.disabled || returnButton.getAttribute('aria-disabled') === 'true') return;
            if (!returnModalInstance || !returnForm || !returnInput) return notify('error', 'No está disponible el modal para editar la fecha de retorno.');
            returnForm.elements.id.value = returnButton.dataset.id || '';
            returnForm.querySelector('[data-salida-return-folio]').textContent = returnButton.dataset.folio || `#${returnButton.dataset.id}`;
            returnInput.min = returnButton.dataset.fechaSalida || '';
            returnInput.value = returnButton.dataset.fechaRetorno || '';
            returnProvider.value = returnButton.dataset.proveedorRetorno || '';
            returnReference.value = returnButton.dataset.referenciaRetorno || '';
            returnNotes.value = returnButton.dataset.notasRetorno || '';
            [returnInput, returnProvider, returnReference].forEach((control) => control.classList.remove('is-invalid'));
            if (window.jQuery && jQuery.fn.select2) jQuery(returnProvider).trigger('change');
            returnForm.dataset.movementId = returnButton.dataset.id || '';
            returnModalInstance.show();
        });

        returnForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const valid = [returnInput, returnProvider, returnReference].every((control) => Boolean(control?.value && control.checkValidity()));
            if (!valid) {
                [returnInput, returnProvider, returnReference].forEach((control) => control?.classList.toggle('is-invalid', !control.value || !control.checkValidity()));
                notify('error', 'Completa la fecha, proveedor y referencia / factura obligatorios.');
                return;
            }
            const saveButton = returnForm.querySelector('[data-salida-return-save]');
            saveButton.disabled = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(salidaList.dataset.salidaReturnUrl, {
                    id: Number(returnForm.dataset.movementId), fecha_retorno: returnInput.value,
                    id_proveedor: Number(returnProvider.value), referencia: returnReference.value.trim(), notas: returnNotes.value.trim(),
                }, { headers: { 'X-CSRF-TOKEN': csrfToken } });
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo actualizar el retorno.');
                notify('success', response.data.message || 'Retorno actualizado.');
                const movementId = Number(returnForm.dataset.movementId);
                const row = Array.from(table.querySelectorAll('tbody tr')).find((item) => Number(item.querySelector('[data-salida-return]')?.dataset.id) === movementId);
                if (row) {
                    const detailButton = row.querySelector('[data-salida-detail]');
                    if (detailButton) {
                        detailButton.dataset.retorno = formatReturnDate(returnInput.value);
                        detailButton.dataset.retornoRaw = returnInput.value;
                    }
                    const returnButton = row.querySelector('[data-salida-return]');
                    if (returnButton) returnButton.dataset.fechaRetorno = returnInput.value;
                    if (returnButton) {
                        returnButton.dataset.proveedorRetorno = returnProvider.value;
                        returnButton.dataset.referenciaRetorno = returnReference.value.trim();
                        returnButton.dataset.notasRetorno = returnNotes.value.trim();
                    }
                    jQuery(row).find('td').eq(5).attr('data-order', returnInput.value);
                    jQuery(table).DataTable().cell(row, 5).data(formatReturnDate(returnInput.value)).draw(false);
                    if (response.data?.returned) {
                        const dataTable = jQuery(table).DataTable();
                        dataTable.cell(row, 6).data('<span class="badge bg-success text-white" data-salida-return-state>Devuelto a la estación de origen</span>').draw(false);
                        const updatedRow = Array.from(table.querySelectorAll('tbody tr')).find((item) => Number(item.querySelector('[data-salida-return]')?.dataset.id) === movementId);
                        const editReturn = updatedRow?.querySelector('[data-salida-return]');
                        if (editReturn) {
                            editReturn.disabled = true;
                            editReturn.classList.add('disabled');
                            editReturn.setAttribute('aria-disabled', 'true');
                            editReturn.title = 'El retorno ya fue procesado';
                        }
                        if (detailButton) {
                            detailButton.dataset.estadoRetorno = 'Devuelto a la estación de origen';
                            detailButton.dataset.estadoRetornoClass = 'bg-success text-white';
                        }
                    }
                }
                returnModalInstance?.hide();
            } catch (error) {
                notify('error', error.response?.data?.message || error.message || 'No se pudo actualizar el retorno.');
            } finally {
                saveButton.disabled = false;
            }
        });

        const processDueReturns = async () => {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(salidaList.dataset.salidaProcessUrl, {}, { headers: { 'X-CSRF-TOKEN': csrfToken } });
                if (!response.data?.success || !response.data?.ids?.length) return;
                const dataTable = jQuery(table).DataTable();
                response.data.ids.forEach((id) => {
                    const row = Array.from(table.querySelectorAll('tbody tr')).find((item) => Number(item.querySelector('[data-salida-return]')?.dataset.id) === Number(id));
                    if (row) dataTable.cell(row, 6).data('<span class="badge bg-success text-white" data-salida-return-state>Devuelto a la estación de origen</span>');
                });
                dataTable.draw(false);
                response.data.ids.forEach((id) => {
                    const row = Array.from(table.querySelectorAll('tbody tr')).find((item) => Number(item.querySelector('[data-salida-return]')?.dataset.id) === Number(id));
                    const editReturn = row?.querySelector('[data-salida-return]');
                    if (editReturn) {
                        editReturn.disabled = true;
                        editReturn.classList.add('disabled');
                        editReturn.setAttribute('aria-disabled', 'true');
                        editReturn.title = 'El retorno ya fue procesado';
                    }
                    const detailButton = row?.querySelector('[data-salida-detail]');
                    if (detailButton) {
                        detailButton.dataset.estadoRetorno = 'Devuelto a la estación de origen';
                        detailButton.dataset.estadoRetornoClass = 'bg-success text-white';
                    }
                });
                notify('success', 'Se repuso el inventario de las devoluciones que llegaron a su fecha.');
            } catch (error) {
                console.error('No se pudieron procesar las devoluciones programadas.', error);
            }
        };
        window.setInterval(processDueReturns, 60_000);
    }

    const setupSignature = (section) => {
        const canvas = section?.querySelector('[data-signature-canvas]');
        const hidden = section?.querySelector('[data-signature-value]');
        const clearButton = section?.querySelector('[data-signature-clear]');
        const container = canvas?.closest('[data-signature-wrapper]') || canvas;
        if (!canvas || !hidden || typeof window.SignaturePad === 'undefined') return null;

        const resize = () => {
            if (!canvas.offsetWidth || !canvas.offsetHeight) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const width = canvas.offsetWidth;
            const height = canvas.offsetHeight;
            if (canvas.width === Math.round(width * ratio) && canvas.height === Math.round(height * ratio)) return;
            const data = pad && !pad.isEmpty() ? pad.toData() : null;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            canvas.getContext('2d').scale(ratio, ratio);
            if (data) pad.fromData(data);
        };

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)', penColor: 'rgb(20,35,60)' });
        clearButton?.addEventListener('click', () => { pad.clear(); hidden.value = ''; container.classList.remove('border-danger'); });
        window.addEventListener('resize', resize);
        if (window.ResizeObserver && container) {
            const resizeObserver = new ResizeObserver(resize);
            resizeObserver.observe(container);
        }
        requestAnimationFrame(resize);
        return { pad, hidden, canvas, container };
    };

    const salidaSection = document.querySelector('[data-salida-form]');
    if (salidaSection) {
        const form = document.getElementById('form-refacciones-salida');
        const product = salidaSection.querySelector('[data-salida-product]');
        const quantity = salidaSection.querySelector('[data-salida-quantity]');
        const stockLabel = salidaSection.querySelector('[data-salida-stock]');
        const stockError = salidaSection.querySelector('[data-salida-stock-error]');
        const button = salidaSection.querySelector('[data-salida-submit]');
        const signature = setupSignature(salidaSection);
        let lastProductId = product?.value || '';

        if (window.jQuery && jQuery.fn.select2 && product) {
            jQuery(product).select2({ width: '100%', language: 'es', placeholder: 'Selecciona una refacción' });
        }

        const availableStock = () => Number(product?.selectedOptions?.[0]?.dataset?.stock || 0);
        const updateAvailability = () => {
            const selected = Boolean(product?.value);
            const stock = availableStock();
            if (product?.value !== lastProductId) {
                if (quantity) quantity.value = '';
                lastProductId = product?.value || '';
            }
            if (quantity) {
                quantity.disabled = !selected;
                if (selected) quantity.max = String(stock);
                else quantity.removeAttribute('max');
            }
            if (stockLabel) stockLabel.textContent = selected ? `Stock disponible en ${salidaSection.dataset.originName || 'la estación de origen'}: ${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(stock)}` : 'Selecciona una refacción para consultar su disponibilidad.';
            const invalid = selected && (!quantity?.value || Number(quantity.value) <= 0 || Number(quantity.value) > stock);
            stockError?.classList.toggle('d-none', !invalid);
            if (stockError && invalid) stockError.textContent = Number(quantity?.value || 0) > stock ? `La cantidad excede el stock disponible (${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(stock)}).` : 'Indica una cantidad mayor a cero.';
            quantity?.classList.toggle('is-invalid', Boolean(invalid && quantity.value));
        };
        product?.addEventListener('change', updateAvailability);
        quantity?.addEventListener('input', updateAvailability);
        if (window.jQuery && product) jQuery(product).on('change.refaccionesSalida', updateAvailability);

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (salidaSection.dataset.mode === 'pending-edit') {
                const date = form.querySelector('[name="fecha"]');
                const reason = form.querySelector('[name="motivo"]');
                const invalid = !date?.value || !date.checkValidity() || !reason?.value.trim() || !signature || signature.pad.isEmpty();
                [date, reason].forEach((control) => control?.classList.toggle('is-invalid', !control.value || !control.checkValidity()));
                if (signature?.pad.isEmpty()) signature.container.classList.add('border-danger');
                if (invalid) return notify('error', 'Completa la fecha de salida, el motivo y la firma.');

                const original = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando';
                try {
                    signature.hidden.value = signature.pad.toDataURL('image/png');
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await axios.post(salidaSection.dataset.endpoint, new FormData(form), {
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    });
                    if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo actualizar la salida pendiente.');
                    notify('success', response.data.message || 'Salida actualizada.');
                    window.setTimeout(() => { window.location.href = salidaSection.dataset.returnUrl; }, 600);
                } catch (error) {
                    notify('error', error.response?.data?.message || error.message || 'No se pudo actualizar la salida pendiente.');
                    button.disabled = false;
                    button.innerHTML = original;
                }
                return;
            }
            updateAvailability();
            const destination = form.querySelector('[name="id_estacion_destino"]');
            const date = form.querySelector('[name="fecha"]');
            const reason = form.querySelector('[name="motivo"]');
            const missing = !product?.value || !destination?.value || !date?.value || !reason?.value.trim() || !quantity?.value || Number(quantity.value) > availableStock() || !signature || signature.pad.isEmpty();
            if (missing) {
                [product, quantity, destination, date, reason].forEach((control) => control?.classList.toggle('is-invalid', !control.value || (control === quantity && Number(control.value) > availableStock())));
                if (signature?.pad.isEmpty()) signature.container.classList.add('border-danger');
                notify('error', 'Completa los campos obligatorios y agrega la firma.');
                return;
            }
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando';
            try {
                signature.hidden.value = signature.pad.toDataURL('image/png');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(salidaSection.dataset.endpoint, new FormData(form), {
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo registrar la salida.');
                notify('success', response.data.message || 'Salida registrada.');
                window.setTimeout(() => { window.location.href = salidaSection.dataset.returnUrl; }, 600);
            } catch (error) {
                notify('error', error.response?.data?.message || error.message || 'No se pudo registrar la salida.');
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    }

    const receiveSection = document.querySelector('[data-salida-signature]');
    const receiveForm = document.getElementById('form-refacciones-recepcion');
    const authorForm = document.getElementById('form-refacciones-elabora');
    if (receiveSection && authorForm) {
        const signature = setupSignature(receiveSection);
        const button = receiveSection.querySelector('[data-elabora-submit]');
        authorForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!signature || signature.pad.isEmpty()) {
                signature?.container.classList.add('border-danger');
                notify('error', 'Agrega la firma de quien elabora.');
                return;
            }
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando';
            try {
                signature.hidden.value = signature.pad.toDataURL('image/png');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(receiveSection.dataset.endpoint, new FormData(authorForm), {
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo registrar la firma de quien elabora.');
                notify('success', response.data.message || 'Firma de quien elabora registrada.');
                window.setTimeout(() => { window.location.href = receiveSection.dataset.returnUrl; }, 600);
            } catch (error) {
                notify('error', error.response?.data?.message || error.message || 'No se pudo registrar la firma de quien elabora.');
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    }
    if (receiveSection && receiveForm) {
        const signature = setupSignature(receiveSection);
        const employee = receiveForm.querySelector('[data-receiver-employee]');
        const button = receiveSection.querySelector('[data-receiver-submit]');
        if (window.jQuery && jQuery.fn.select2 && employee) jQuery(employee).select2({ width: '100%', language: 'es', placeholder: 'Selecciona al encargado' });

        receiveForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!employee?.value || !signature || signature.pad.isEmpty()) {
                employee?.classList.toggle('is-invalid', !employee.value);
                if (signature?.pad.isEmpty()) signature.container.classList.add('border-danger');
                notify('error', 'Selecciona al encargado y agrega la firma de recepción.');
                return;
            }
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando';
            try {
                signature.hidden.value = signature.pad.toDataURL('image/png');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(receiveSection.dataset.endpoint, new FormData(receiveForm), {
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo registrar la firma.');
                notify('success', response.data.message || 'Recepción firmada.');
                window.setTimeout(() => { window.location.href = receiveSection.dataset.returnUrl; }, 600);
            } catch (error) {
                notify('error', error.response?.data?.message || error.message || 'No se pudo registrar la firma.');
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    }
})();
