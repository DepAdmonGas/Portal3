document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('orden-mantenimiento-app');
    if (!app) return;

    const base = app.dataset.baseUrl;
    const moduleKey = app.dataset.moduleKey;
    const tableSelector = '#tabla-orden-mantenimiento';
    const table = $(tableSelector);
    const canEdit = app.dataset.puedeEditar === 'true';
    const canDelete = app.dataset.puedeEliminar === 'true';
    const record = JSON.parse(app.dataset.record || 'null');
    let activeCommentId = 0;
    let activeEvidenceId = 0;
    let evidenceBusy = false;
    let activeCauseId = 0;
    let causeBusy = false;
    let exportBusy = false;
    let serviceSaveQueue = Promise.resolve();
    const serviceSaveTimers = {};
    const confirmedServiceValues = {};

    const openAreaOptions = areaId => {
        const area = (record?.areas || []).find(item => Number(item.id) === Number(areaId));
        if (!area || !area.estatus) return;
        const options = area.subareas_disponibles || [];
        if (options.length === 0) return;
        document.getElementById('orden-opciones-area-titulo').innerHTML = '<i class="ti ti-map-pin me-1"></i> ' + esc(area.area);
        document.getElementById('orden-opciones-area-lista').innerHTML = '<table class="table table-striped table-bordered align-middle w-100 mb-0"><tbody>' + options.map(option =>
            '<tr><td class="align-middle">' + esc(option.nombre) + '</td><td class="text-center align-middle" style="width:58px"><input class="form-check-input js-subarea-status m-0" type="checkbox" data-id="' + Number(option.id) + '" data-parent-area-id="' + Number(area.id) + '" aria-label="Seleccionar ' + esc(option.nombre) + '" ' + (!canEdit || ![0, 1].includes(Number(record.estatus)) ? 'disabled' : '') + ' ' + (Number(option.estatus) === 1 ? 'checked' : '') + '></td></tr>'
        ).join('') + '</tbody></table>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-opciones-area-orden')).show();
    };

    const setEvidenceControlsBusy = busy => {
        const modal = document.getElementById('modal-evidencia-orden');
        if (!modal) return;
        modal.querySelectorAll('button, input[type="file"]').forEach(button => {
            if (busy) {
                if (button.dataset.evidenceWasDisabled === undefined) {
                    button.dataset.evidenceWasDisabled = button.disabled ? '1' : '0';
                }
                button.disabled = true;
                return;
            }

            if (button.dataset.evidenceWasDisabled !== undefined) {
                button.disabled = button.dataset.evidenceWasDisabled === '1';
                delete button.dataset.evidenceWasDisabled;
            } else if (button.matches('.js-orden-evidence-delete, #orden-modal-evidencia-agregar')) {
                button.disabled = !canEdit;
            }
        });
    };

    const withEvidenceLoader = async (button, pendingText, action) => {
        if (evidenceBusy) return null;
        evidenceBusy = true;
        const originalContent = button.innerHTML;
        setEvidenceControlsBusy(true);
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + pendingText;
        window.loader?.show();
        try {
            return await action();
        } finally {
            window.loader?.hide();
            evidenceBusy = false;
            setEvidenceControlsBusy(false);
            if (button.isConnected) button.innerHTML = originalContent;
        }
    };

    const esc = (value) => $('<div>').text(value == null ? '' : String(value)).html();
    const dateLabel = (label, raw) => {
        const currentLabel = String(label ?? '').trim();
        if (currentLabel && currentLabel !== 'S/I') return currentLabel;

        const source = String(raw ?? '').trim();
        let parts = source.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!parts) {
            const legacyParts = source.match(/^(\d{2})[-/](\d{2})[-/](\d{4})/);
            if (legacyParts) parts = [legacyParts[0], legacyParts[3], legacyParts[2], legacyParts[1]];
        }
        if (!parts) return currentLabel || source || 'S/I';

        const year = Number(parts[1]);
        const month = Number(parts[2]);
        const day = Number(parts[3]);
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return currentLabel || 'S/I';
        }
        const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return String(day).padStart(2, '0') + ' de ' + months[month - 1] + ' del ' + year;
    };
    const signatureDateLabel = signature => {
        const label = String(signature?.fecha ?? '').trim();
        if (label && label !== 'S/I') return label;

        const raw = String(signature?.fecha_raw ?? '').trim();
        const time = raw.match(/(?:T|\s)(\d{2}):(\d{2})(?::\d{2})?/);
        const date = dateLabel('', raw);
        if (!time || date === 'S/I') return label || date;

        const hour24 = Number(time[1]);
        const hour12 = hour24 % 12 || 12;
        return date + ', ' + hour12 + ':' + time[2] + ' ' + (hour24 >= 12 ? 'pm' : 'am');
    };
    const currentContextIsAll = () => {
        // The shared selector class is not exposed on window. Read its native
        // select so a selected station does not get mistaken for "all".
        const selector = document.getElementById('module-station-selector-' + moduleKey);
        // If the selector is omitted for a user with only one available
        // location, keep the table in that location's legacy layout.
        return Boolean(selector && !selector.value);
    };
    const affectationHeading = () => {
        const selector = document.getElementById('module-station-selector-' + moduleKey);
        if (!selector || !selector.value) return currentContextIsAll()
            ? '¿En qué afecta a la estación / departamento?'
            : '¿En qué afecta a la estación?';
        if (selector.value === 'depto_9') return '¿En qué afecta al autolavado?';
        return '¿En qué afecta a la estación?';
    };
    const reloadTable = () => {
        if ($.fn.DataTable.isDataTable(table)) table.DataTable().ajax.reload(null, false);
    };

    if (table.length) {
        table.DataTable({
            processing: true,
            serverSide: false,
            ajax: { url: base + '/data', type: 'GET', cache: false, dataSrc: json => json?.success ? (json.data || []) : [] },
            order: [[0, 'desc']],
            pageLength: 15,
            lengthMenu: [15, 30, 50, 100],
            autoWidth: false,
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columns: [
                { title: 'Folio', data: 'folio_label', className: 'text-center align-middle text-nowrap', width: '72px' },
                { title: 'Estación / Departamento', data: 'estacion', className: 'text-center align-middle text-nowrap', visible: currentContextIsAll(), searchable: true, width: '145px' },
                { title: 'Fecha', data: 'fecha', className: 'text-center align-middle text-nowrap', width: '165px' },
                { title: 'Asunto', data: 'asunto', className: 'align-middle', defaultContent: '', width: '260px' },
                { title: affectationHeading(), data: 'afectacion', className: 'align-middle', width: '320px' },
                { title: 'Estatus', data: 'estatus_texto', className: 'text-center align-middle', width: '210px', render: (v, t, row) => {
                    const done = ['Refacción Instalada', 'Orden cerrada'].includes(row.estatus_texto);
                    const processLabel = row.firma_b ? 'En proceso: Autorización' : 'En proceso: Vo.Bo.';
                    const label = Number(row.estatus) === 0 ? 'Pendiente' : (Number(row.estatus) === 1 ? processLabel : v);
                    const color = Number(row.estatus) === 0 ? 'bg-danger text-white' : (Number(row.estatus) === 1 ? 'bg-warning text-white' : (done ? 'bg-success text-white' : 'bg-danger text-white'));
                    return '<span class="badge ' + color + '">' + esc(label) + '</span>';
                } },
                { title: 'Firma', data: null, className: 'text-center align-middle', orderable: false, searchable: false, width: '70px',
                    render: (v, t, row) => row.puede_firmar ? '<a class="d-inline-flex align-items-center justify-content-center text-decoration-none" href="' + base + '-firma/' + Number(row.id) + '" title="Firmar" aria-label="Firmar"><i class="ti ti-clock text-primary fs-8"></i></a>' : (row.estatus < 1 ? '<i class="ti ti-clock text-muted fs-8" title="Firma pendiente" aria-label="Firma pendiente"></i>' : '<i class="ti ti-clock text-muted fs-8" title="Firma no disponible" aria-label="Firma no disponible"></i>') },
                { title: '<i class="ti ti-message fs-7" title="Comentarios"></i>', data: null, className: 'text-center align-middle', orderable: false, searchable: false, width: '42px',
                    render: (v, t, row) => {
                        const badge = row.comentarios > 0
                            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.comentarios + '</span>'
                            : '';
                        return '<a href="#" class="btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center js-orden-comments" data-id="' + row.id + '" title="Comentarios" aria-label="Abrir comentarios">'
                            + '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
                    } },
                { title: '<i class="ti ti-dots-vertical fs-6" title="Opciones"></i>', data: null, className: 'text-center align-middle', orderable: false, searchable: false, width: '42px',
                    render: (v, t, row) => {
                        const item = (label, icon, enabled, extraClass = '', attributes = '') => '<a class="dropdown-item ' + (enabled ? 'pointer ' : 'disabled ') + extraClass + '" ' + (enabled ? attributes : 'aria-disabled="true" tabindex="-1"') + '><i class="ti ' + icon + ' me-1"></i> ' + label + '</a>';
                        const exportItem = (label, icon, enabled, format) => '<a class="dropdown-item ' + (enabled ? 'pointer js-orden-export" href="' + base + '/' + row.id + '/' + format + '" data-format="' + format : 'disabled" aria-disabled="true" tabindex="-1') + '"><i class="ti ' + icon + ' fs-6 me-1"></i> ' + label + '</a>';
                        return '<div class="dropdown dropstart"><a href="javascript:void(0)" class="pointer" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">' +
                            item('Detalle', 'ti-eye', true, 'js-orden-detail', 'data-id="' + row.id + '"') +
                            item('Evidencia', 'ti-photo', row.puede_ver_evidencia, row.puede_ver_evidencia ? 'js-orden-evidence' : '', 'data-id="' + row.id + '"') +
                            item('Causa', 'ti-help-circle', row.puede_ver_causa, row.puede_ver_causa ? 'js-orden-cause' : '', 'data-id="' + row.id + '"') +
                            exportItem('Descargar PDF', 'ti-file-type-pdf', row.puede_descargar, 'pdf') +
                            exportItem('Descargar Excel', 'ti-file-spreadsheet', row.puede_descargar, 'excel') +
                            item('Editar', 'ti-pencil', canEdit && row.puede_editar, 'js-orden-edit', 'data-id="' + row.id + '"') +
                            item('Eliminar', 'ti-trash', canDelete && row.puede_eliminar, 'js-orden-delete text-danger', 'data-id="' + row.id + '" data-name="Folio ' + esc(row.folio_label) + '"') +
                            '</div></div>';
                    } }
            ],
            drawCallback: function () {
                const api = this.api();
                api.column(1).visible(currentContextIsAll());
                $(api.column(4).header()).html(affectationHeading());
            }
        });
    }

    const downloadOrderExport = async link => {
        if (exportBusy || !link?.href) return;
        exportBusy = true;
        link.classList.add('disabled');
        window.loader?.show();
        try {
            const response = await fetch(link.href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const blob = await response.blob();
            if (!response.ok) {
                const message = (await blob.text()).trim();
                throw new Error(message || 'No se pudo descargar el archivo.');
            }

            const contentType = (response.headers.get('Content-Type') || blob.type || '').toLowerCase();
            const expectedType = link.dataset.format === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            if (!contentType.includes(expectedType)) {
                throw new Error('El servidor no generó el archivo solicitado.');
            }

            const disposition = response.headers.get('Content-Disposition') || '';
            const encodedFilename = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const plainFilename = disposition.match(/filename="?([^";]+)"?/i)?.[1];
            let filename = 'orden-mantenimiento.' + (link.dataset.format === 'pdf' ? 'pdf' : 'xlsx');
            if (encodedFilename) {
                try { filename = decodeURIComponent(encodedFilename); } catch (_) { filename = encodedFilename; }
            } else if (plainFilename) {
                filename = plainFilename;
            }

            const objectUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = objectUrl;
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        } catch (error) {
            Notify.error(error.message || 'No se pudo descargar el archivo.');
        } finally {
            window.loader?.hide();
            exportBusy = false;
            if (link.isConnected) link.classList.remove('disabled');
        }
    };

    table.on('click', 'a.js-orden-export', event => {
        event.preventDefault();
        downloadOrderExport(event.target.closest('a.js-orden-export'));
    });

    document.getElementById('orden-nuevo')?.addEventListener('click', async () => {
        const button = document.getElementById('orden-nuevo');
        button.disabled = true;
        try {
            const response = await axios.post(base + '/crear', {});
            if (response.data?.success) location.href = base + '/' + response.data.id;
            else Notify.error(response.data?.message || 'No se pudo crear la solicitud.');
        } catch (error) {
            Notify.error(error.response?.data?.message || 'No se pudo crear la solicitud.');
        } finally { button.disabled = false; }
    });

    if (record) {
        document.getElementById('orden-folio').textContent = record.folio_label;
        document.getElementById('orden-razon-social').textContent = record.razon_social || 'S/I';
        document.getElementById('orden-fecha').textContent = dateLabel(record.fecha_label, record.fecha);
        document.getElementById('orden-afectacion').value = record.afectacion === 'S/I' ? '' : record.afectacion;
        document.getElementById('orden-tipo').value = record.tipo_servicio;
        document.getElementById('orden-trabajo').value = record.orden_trabajo;
        document.getElementById('orden-riesgo').value = record.orden_riesgo;
        document.getElementById('orden-comentarios').value = record.comentarios_generales;
        confirmedServiceValues.afectacion = document.getElementById('orden-afectacion').value;
        confirmedServiceValues.comentarios = document.getElementById('orden-comentarios').value;
        confirmedServiceValues.tipo_servicio = String(record.tipo_servicio);
        confirmedServiceValues.orden_trabajo = String(record.orden_trabajo);
        confirmedServiceValues.orden_riesgo = String(record.orden_riesgo);
        document.getElementById('orden-finalizar').hidden = !canEdit || record.estatus !== 0;
        document.getElementById('orden-firma-card').hidden = !canEdit || record.estatus !== 0;
        document.querySelectorAll('.js-orden-choice').forEach(input => {
            const fieldId = input.name === 'orden-tipo-radio' ? 'orden-tipo' : (input.name === 'orden-trabajo-radio' ? 'orden-trabajo' : 'orden-riesgo');
            input.checked = String(input.value) === String(document.getElementById(fieldId).value);
            input.disabled = !canEdit || ![0, 1].includes(record.estatus);
        });
        ['orden-afectacion', 'orden-tipo', 'orden-trabajo', 'orden-riesgo', 'orden-comentarios'].forEach(id => {
            const field = document.getElementById(id);
            if (field) field.disabled = !canEdit || ![0, 1].includes(record.estatus);
        });
        const areas = document.getElementById('orden-areas');
        const allowChanges = canEdit && [0, 1].includes(record.estatus);
        document.getElementById('orden-agregar-area').hidden = !allowChanges;
        document.getElementById('orden-agregar-evidencia').hidden = !allowChanges;
        const areaRows = (record.areas || []).map(area => {
            const hasOptions = (area.subareas_disponibles || []).length > 0;
            const selectedSubareas = Number(area.estatus) === 1
                ? (area.subareas_disponibles || []).filter(item => Number(item.estatus) === 1).map(item => item.nombre)
                : [];
            const subareaSummary = selectedSubareas.length
                ? '<small class="d-block text-muted mt-1 js-area-selected-subareas" data-area-id="' + Number(area.id) + '">(' + esc(selectedSubareas.join(', ')) + ')</small>'
                : '<small class="d-block text-muted mt-1 js-area-selected-subareas" data-area-id="' + Number(area.id) + '" hidden></small>';
            const optionsButton = hasOptions && Number(area.estatus) === 1
                ? '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none js-area-options" data-id="' + Number(area.id) + '" aria-label="Seleccionar opciones de ' + esc(area.area) + '" title="Opciones del área"><i class="ti ti-list-details fs-6"></i></button>'
                : '';
            return '<tr><td class="align-middle"><span>' + esc(area.area) + '</span>' + subareaSummary + '</td><td class="text-center align-middle p-0" style="width:48px;min-width:48px"><div class="d-flex align-items-center justify-content-center h-100">' + optionsButton + '</div></td><td class="text-center align-middle p-0" style="width:58px"><input class="form-check-input js-area-status m-0" type="checkbox" data-id="' + Number(area.id) + '" aria-label="Seleccionar ' + esc(area.area) + '" ' + (allowChanges ? '' : 'disabled') + ' ' + (area.estatus ? 'checked' : '') + '></td></tr>';
        }).join('');
        areas.innerHTML = '<table class="table table-striped table-bordered align-middle w-100 mb-0"><tbody>' + (areaRows || '<tr><td colspan="3" class="text-center text-primary">No se encontró información</td></tr>') + '</tbody></table>';
        const materials = document.getElementById('orden-materiales');
        document.getElementById('orden-agregar-refaccion').hidden = !allowChanges;
        materials.innerHTML = '<table class="table table-bordered mb-0 table-striped align-middle w-100"><thead><tr><th>Refacción</th><th class="text-center align-middle">Cantidad</th><th>Nota</th><th class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><i class="ti ti-trash fs-6 text-danger" title="Eliminar" aria-label="Eliminar"></i></th></tr></thead><tbody>' +
            ((record.materiales || []).map(item => '<tr><td>' + esc(item.concepto) + '</td><td class="text-center align-middle">' + esc(item.cantidad) + '</td><td>' + esc(item.nota) + '</td><td class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><button class="btn btn-link p-0 text-decoration-none js-material-delete ' + (allowChanges ? '' : 'disabled') + '" data-id="' + item.id + '" data-name="' + esc(item.concepto) + '" ' + (allowChanges ? '' : 'disabled') + '><i class="ti ti-trash fs-6 text-danger"></i></button></td></tr>').join('') || '<tr><td colspan="4" class="text-center text-primary">No se encontró información</td></tr>') + '</tbody></table>';
        document.getElementById('orden-subir-instalacion').hidden = !(canEdit && [2, 3].includes(record.estatus));
        document.getElementById('orden-subir-evidencia').hidden = !allowChanges;
        loadFiles();
        window.setTimeout(() => {
            const target = location.hash ? document.querySelector(location.hash) : null;
            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
        if (Number(record.id_estacion) === 9) {
            const affectationLabel = document.querySelector('label[for="orden-afectacion"]');
            if (affectationLabel) affectationLabel.textContent = '¿En qué afecta al autolavado?';
        }
    }

    async function loadFiles() {
        if (!record) return;
        try {
            const { data } = await axios.post(base + '/archivos', { id: record.id });
            if (!data.success) throw new Error(data.message || 'No se pudieron consultar los archivos.');
            const files = data.data;
            const installationFiles = files.instalaciones || [];
            const installationList = document.getElementById('orden-lista-instalacion');
            installationList.hidden = installationFiles.length === 0;
            installationList.innerHTML = installationFiles.length
                ? '<ul class="list-group list-group-flush">' + installationFiles.map(file => '<li class="list-group-item d-flex justify-content-between align-items-center px-0"><span><i class="ti ti-file me-1"></i>' + esc(file.archivo) + '</span><a class="btn btn-sm btn-outline-primary" href="' + esc(file.url) + '"><i class="ti ti-download"></i> Descargar</a></li>').join('') + '</ul>'
                : '';
            document.getElementById('orden-lista-evidencias').innerHTML = '<table class="table table-striped table-bordered align-middle text-center w-100 mb-0"><thead><tr><th class="text-center align-middle" width="96px" style="width:96px;min-width:96px;max-width:96px">#</th><th class="text-center align-middle">Área</th><th class="text-center align-middle">Motivo</th><th class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><i class="ti ti-download fs-6 text-primary" title="Descargar" aria-label="Descargar"></i></th><th class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><i class="ti ti-trash fs-6 text-danger" title="Eliminar" aria-label="Eliminar"></i></th></tr></thead><tbody>' + ((files.evidencias || []).map(file => '<tr><td class="text-center align-middle" width="96px" style="width:96px;min-width:96px;max-width:96px">' + Number(file.id) + '</td><td class="text-center align-middle">' + esc(file.area) + '</td><td class="text-center align-middle">' + esc(file.motivo) + '</td><td class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><a class="btn btn-link p-0 text-decoration-none" href="' + esc(file.url) + '"><i class="ti ti-download fs-6 text-primary"></i></a></td><td class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><button class="btn btn-link p-0 text-decoration-none js-evidence-delete" data-id="' + Number(file.id) + '" data-name="#' + Number(file.id) + '" ' + (!canEdit ? 'disabled' : '') + '><i class="ti ti-trash fs-6 text-danger"></i></button></td></tr>').join('') || '<tr><td colspan="5" class="text-center text-primary">No se encontró información</td></tr>') + '</tbody></table>';
        } catch (error) {
            ['orden-lista-instalacion', 'orden-lista-evidencias'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.hidden = false;
                    element.innerHTML = '<div class="text-danger">' + esc(error.response?.data?.message || error.message) + '</div>';
                }
            });
        }
    }

    const loadEvidenceModalFiles = async id => {
        const list = document.getElementById('orden-modal-evidencia-lista');
        list.innerHTML = '<div class="col-12 text-center py-4"><span class="spinner-border spinner-border-sm text-primary" role="status"></span></div>';
        const { data } = await axios.post(base + '/archivos', { id });
        if (!data.success) throw new Error(data.message || 'No se pudieron cargar las evidencias.');
        const files = data.data?.instalaciones || [];
        list.innerHTML = files.length ? files.map(file => {
            const extension = String(file.archivo || '').split('.').pop().toLowerCase();
            const isPdf = extension === 'pdf';
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
            const previewUrl = esc(file.preview_url || file.url);
            const iconClass = isPdf ? 'ti ti-file-type-pdf text-danger' : (isImage ? 'ti ti-photo text-primary' : 'ti ti-file-description text-primary');
            let preview = '<div class="text-center p-4 bg-white d-flex align-items-center justify-content-center h-100 w-100"><i class="' + iconClass + ' display-4"></i></div>';
            if (isImage) {
                preview = '<img src="' + previewUrl + '" class="w-100 h-100" style="object-fit:cover" alt="Vista previa de evidencia">';
            }
            const fileName = esc(file.archivo || 'Evidencia');
            return '<div class="col-md-6 mt-4"><div class="card h-100 shadow-sm border bg-light position-relative">' +
                '<div class="position-absolute top-0 end-0 p-2" style="z-index:2"><span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width:32px;height:32px"><i class="' + iconClass + ' fs-6"></i></span></div>' +
                '<a href="' + previewUrl + '" target="_blank" rel="noopener" class="text-decoration-none overflow-hidden d-block position-relative" style="height:140px">' + preview + '</a>' +
                '<div class="card-body border-top pb-2 bg-white"><a href="' + previewUrl + '" target="_blank" rel="noopener" class="text-dark text-decoration-none"><p class="small fw-bold text-truncate mb-0" title="' + fileName + '">' + fileName + '</p></a></div>' +
                '<div class="card-footer bg-white"><div class="d-flex align-items-center gap-2"><a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-1" href="' + esc(file.url) + '" title="Descargar"><i class="ti ti-download"></i><span>Descargar</span></a>' +
                (canEdit ? '<button type="button" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-1 js-orden-evidence-delete" data-id="' + Number(file.id) + '" data-name="' + fileName + '" title="Eliminar" ' + (evidenceBusy ? 'disabled' : '') + '><i class="ti ti-trash"></i><span>Eliminar</span></button>' : '') + '</div></div></div></div>';
        }).join('') : '<div class="col-12"><div class="d-flex flex-column align-items-center justify-content-center text-center border rounded p-4 text-muted"><i class="ti ti-photo-off mb-2" style="font-size:48px"></i><div>No se encontró información</div></div></div>';
    };

    const openEvidenceModal = async (id, folioLabel = '') => {
        activeEvidenceId = Number(id);
        document.getElementById('orden-evidencia-folio').textContent = folioLabel ? '(#' + folioLabel + ')' : '';
        document.getElementById('orden-modal-evidencia-archivo').value = '';
        document.getElementById('orden-evidencia-subir').hidden = !canEdit;
        document.getElementById('orden-modal-evidencia-agregar').hidden = !canEdit;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-evidencia-orden')).show();
        try {
            await loadEvidenceModalFiles(activeEvidenceId);
        } catch (error) {
            document.getElementById('orden-modal-evidencia-lista').innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">' + esc(error.response?.data?.message || error.message) + '</div></div>';
        }
    };

    const loadCauseModalFiles = async id => {
        const list = document.getElementById('orden-modal-causa-lista');
        list.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary" role="status"></span></div>';
        const { data } = await axios.post(base + '/archivos', { id });
        if (!data.success) throw new Error(data.message || 'No se pudieron cargar las causas.');
        const causes = data.data?.causas || [];
            list.innerHTML = '<table class="table table-striped table-bordered align-middle w-100 mb-0"><thead><tr>' +
            '<th class="text-center">No.</th><th>Fecha</th><th>Descripción</th><th class="text-center align-middle">Se queda en</th><th class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><i class="ti ti-file-type-pdf fs-6 text-danger" title="Factura PDF" aria-label="Factura PDF"></i></th><th class="text-end">Precio</th><th class="text-center align-middle" width="48px" style="width:48px;min-width:48px"><i class="ti ti-trash fs-6 text-danger" title="Eliminar" aria-label="Eliminar"></i></th>' +
            '</tr></thead><tbody>' + (causes.map((cause, index) => '<tr>' +
                '<td class="text-center">' + (index + 1) + '</td><td>' + esc(cause.fecha_hora || cause.fecha || 'S/I') + '</td>' +
                '<td>' + esc(cause.descripcion || 'S/I') + '</td><td class="text-center align-middle">' + esc(cause.refaccion || 'S/I') + '</td>' +
                '<td class="text-center align-middle" width="48px" style="width:48px;min-width:48px">' + (cause.pdf_url ? '<a class="pointer text-decoration-none" href="' + esc(cause.pdf_url) + '" title="Descargar factura PDF"><i class="ti ti-file-type-pdf fs-6 text-danger"></i></a>' : '<i class="ti ti-file-off fs-6 text-muted" title="Sin factura PDF" aria-label="Sin factura PDF"></i>') + '</td>' +
                '<td class="text-end">$ ' + esc(cause.precio || '0.00') + '</td>' +
                '<td class="text-center align-middle" width="48px" style="width:48px;min-width:48px">' + (canEdit ? '<button type="button" class="btn btn-link p-0 text-decoration-none js-cause-delete" data-id="' + Number(cause.id) + '" data-name="' + esc(cause.descripcion || 'Causa') + '" title="Eliminar" ' + (causeBusy ? 'disabled' : '') + '><i class="ti ti-trash fs-6 text-danger"></i></button>' : '<button type="button" class="btn btn-link p-0 text-decoration-none disabled" aria-disabled="true" tabindex="-1" title="Sin permiso para eliminar"><i class="ti ti-trash fs-6 text-muted"></i></button>') + '</td></tr>'
            ).join('') || '<tr><td colspan="7" class="text-center text-primary">No se encontró información para mostrar</td></tr>') + '</tbody></table>';
    };

    const openCauseModal = async id => {
        activeCauseId = Number(id);
        ['orden-descripcion-causa', 'orden-refaccion-causa', 'orden-precio-causa', 'orden-pdf-causa'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.value = '';
            field.classList.remove('is-invalid');
        });
        document.getElementById('orden-causa-form').hidden = !canEdit;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-causa-orden')).show();
        try {
            await loadCauseModalFiles(activeCauseId);
        } catch (error) {
            document.getElementById('orden-modal-causa-lista').innerHTML = '<div class="alert alert-danger mb-0">' + esc(error.response?.data?.message || error.message) + '</div>';
        }
    };

    const withCauseLoader = async (button, pendingText, action) => {
        if (causeBusy) return null;
        causeBusy = true;
        const modal = document.getElementById('modal-causa-orden');
        const originalContent = button.innerHTML;
        modal.querySelectorAll('button, input, textarea, select').forEach(control => {
            if (control.dataset.causeWasDisabled === undefined) control.dataset.causeWasDisabled = control.disabled ? '1' : '0';
            control.disabled = true;
        });
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + pendingText;
        window.loader?.show();
        try {
            return await action();
        } finally {
            window.loader?.hide();
            causeBusy = false;
            modal.querySelectorAll('button, input, textarea, select').forEach(control => {
                if (control.dataset.causeWasDisabled !== undefined) {
                    control.disabled = control.dataset.causeWasDisabled === '1';
                    delete control.dataset.causeWasDisabled;
                }
            });
            if (button.isConnected) button.innerHTML = originalContent;
        }
    };

    const queueServiceSave = (field, value) => {
        if (!record || !canEdit || ![0, 1].includes(Number(record.estatus))) return Promise.reject(new Error('La orden ya no permite cambios.'));
        const request = serviceSaveQueue.catch(() => undefined).then(async () => {
            const { data } = await axios.post(base + '/editar', { id: record.id, [field]: value, autosave: 1 });
            if (!data.success) throw new Error(data.message || 'No se pudo guardar el cambio.');
            confirmedServiceValues[field] = String(value);
            return true;
        });
        serviceSaveQueue = request;
        return request;
    };
    const saveTextField = (inputId, field) => {
        const input = document.getElementById(inputId);
        if (!input || !record) return;
        const persist = () => {
            const value = input.value;
            if (String(value) === String(confirmedServiceValues[field] ?? '')) return;
            queueServiceSave(field, value).catch(error => {
                Notify.error(error.response?.data?.message || error.message || 'No se pudo guardar el cambio.');
            });
        };
        input.addEventListener('input', () => {
            input.classList.remove('is-invalid');
            window.clearTimeout(serviceSaveTimers[field]);
            serviceSaveTimers[field] = window.setTimeout(persist, 600);
        });
        input.addEventListener('blur', () => {
            window.clearTimeout(serviceSaveTimers[field]);
            persist();
        });
    };
    if (record) {
        saveTextField('orden-afectacion', 'afectacion');
        saveTextField('orden-comentarios', 'comentarios');
    }
    document.querySelectorAll('.js-orden-choice').forEach(input => input.addEventListener('change', async () => {
        if (!record) return;
        const field = input.name === 'orden-tipo-radio' ? 'tipo_servicio' : (input.name === 'orden-trabajo-radio' ? 'orden_trabajo' : 'orden_riesgo');
        const selectId = field === 'tipo_servicio' ? 'orden-tipo' : (field === 'orden_trabajo' ? 'orden-trabajo' : 'orden-riesgo');
        const group = [...document.querySelectorAll('input[name="' + input.name + '"]')];
        const value = String(input.value);
        document.getElementById(selectId).value = value;
        try {
            await queueServiceSave(field, value);
        } catch (error) {
            const currentSelection = group.find(item => item.checked)?.value;
            if (currentSelection === value) {
                const previous = String(confirmedServiceValues[field] ?? '0');
                document.getElementById(selectId).value = previous;
                group.forEach(item => { item.checked = item.value === previous; });
            }
            Notify.error(error.response?.data?.message || error.message || 'No se pudo guardar la opción.');
        }
    }));

    let sendingComment = false;
    const commentList = document.getElementById('orden-comentarios-lista');
    const renderComments = (comments) => {
        const rows = comments || [];
        commentList.innerHTML = rows.length ? rows.map(comment => {
            if (!comment.propio) {
                return '<div class="d-flex mb-3 justify-content-start"><div class="d-flex gap-3 align-items-start">'
                    + '<div class="flex-shrink-0"><div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px;height:45px"><i class="ti ti-user text-white fs-5"></i></div></div>'
                    + '<div><h6 class="fw-semibold mb-1">' + esc(comment.usuario || 'Usuario') + '</h6><div class="fs-3 text-muted mb-1">' + esc(comment.fecha || '') + '</div><div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width:420px;overflow-wrap:anywhere">' + esc(comment.comentario) + '</div></div>'
                    + '</div></div>';
            }
            return '<div class="d-flex mb-3 justify-content-end"><div class="d-flex flex-column align-items-end"><div class="fs-3 text-muted mb-1 text-end">' + esc(comment.fecha || '') + '</div><div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width:420px;overflow-wrap:anywhere">' + esc(comment.comentario) + '</div></div></div>';
        }).join('') : '<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height:300px"><i class="ti ti-message-off text-muted mb-2" style="font-size:55px"></i><p class="text-muted mb-0 fs-5">Sin comentarios</p></div>';
        commentList.scrollTop = commentList.scrollHeight;
    };
    const loadComments = async (id) => {
        const { data } = await axios.post(base + '/comentarios', { id });
        if (!data.success) throw new Error(data.message || 'No se pudieron cargar los comentarios.');
        const detail = data.detalle || {};
        const value = item => esc(item === null || item === undefined || item === '' ? 'S/I' : item);
        const tipoServicio = ({ 1: 'Preventivo', 2: 'Correctivo', 3: 'Emergente' })[detail.tipo_servicio] || 'S/I';
        document.getElementById('orden-comentarios-folio').textContent = detail.folio_label || id;
        document.getElementById('orden-comentario-detalle').innerHTML =
            '<div class="col-5"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px"><i class="ti ti-hash fs-5"></i></span><div><div class="form-label small mb-0">Folio:</div><div>' + value(detail.folio_label) + '</div></div></div></div>' +
            '<div class="col-7"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px"><i class="ti ti-calendar-event fs-5"></i></span><div><div class="form-label small mb-0">Fecha:</div><div>' + value(dateLabel(detail.fecha_label, detail.fecha)) + '</div></div></div></div>' +
            '<div class="col-12"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-info-subtle text-info flex-shrink-0" style="width:34px;height:34px"><i class="ti ti-map-pin fs-5"></i></span><div><div class="form-label small mb-0">Estación / Departamento:</div><div>' + value(detail.estacion) + '</div></div></div></div>' +            
            '<div class="col-6"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px"><i class="ti ti-settings fs-5"></i></span><div><div class="form-label small mb-0">Tipo de servicio:</div><div>' + tipoServicio + '</div></div></div></div>' +
            '<div class="col-6"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-danger-subtle text-danger flex-shrink-0" style="width:34px;height:34px"><i class="ti ti-flag fs-5"></i></span><div><div class="form-label small mb-0">Estatus:</div><div>' + value(detail.estatus_texto) + '</div></div></div></div>';
        renderComments(data.data);
    };
    const openComments = async (id) => {
        activeCommentId = Number(id);
        document.getElementById('orden-nuevo-comentario').value = '';
        document.getElementById('orden-comentarios-folio').textContent = id;
        document.getElementById('orden-comentario-detalle').innerHTML = '';
        commentList.innerHTML = '<div class="d-flex justify-content-center py-4"><span class="spinner-border spinner-border-sm text-primary" role="status"></span></div>';
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvas-comentarios-orden')).show();
        try { await loadComments(activeCommentId); }
        catch (error) { commentList.innerHTML = '<div class="alert alert-danger mb-0">' + esc(error.response?.data?.message || error.message) + '</div>'; }
    };

    const sendComment = async () => {
        const input = document.getElementById('orden-nuevo-comentario');
        const button = document.getElementById('orden-enviar-comentario');
        const sending = document.getElementById('orden-enviando');
        const text = input.value.trim();
        if (sendingComment || !activeCommentId || !text) return;
        sendingComment = true;
        button.disabled = true;
        input.disabled = true;
        sending.hidden = false;
        commentList.scrollTop = commentList.scrollHeight;
        try {
            const { data } = await axios.post(base + '/comentarios/guardar', { id: activeCommentId, comentario: text });
            if (!data.success) throw new Error(data.message || 'No se pudo guardar el comentario.');
            input.value = '';
            await loadComments(activeCommentId);
            const dt = $.fn.DataTable.isDataTable(table) ? table.DataTable() : null;
            if (dt) {
                dt.rows().every(function () {
                    const row = this.data();
                    if (Number(row.id) === activeCommentId) {
                        row.comentarios = (Number(row.comentarios) || 0) + 1;
                        this.invalidate();
                    }
                });
                dt.draw(false);
            }
        } catch (error) {
            Notify.error(error.response?.data?.message || error.message || 'No se pudo guardar el comentario.');
        } finally {
            sendingComment = false;
            button.disabled = false;
            input.disabled = false;
            sending.hidden = true;
            input.focus();
        }
    };

    table.on('click', '.js-orden-comments', event => { event.preventDefault(); openComments(event.currentTarget.dataset.id); });
    document.getElementById('orden-enviar-comentario')?.addEventListener('click', sendComment);
    document.getElementById('orden-nuevo-comentario')?.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendComment(); }
    });
    const showDetail = async id => {
        try {
            const { data } = await axios.post(base + '/detalle', { id });
            if (!data.success) throw new Error(data.message || 'No se pudo abrir el detalle.');
            const detail = data.data;
            const asList = value => {
                if (Array.isArray(value)) return value;
                if (value && typeof value === 'object') return Object.values(value);
                if (typeof value === 'string') {
                    try {
                        const parsed = JSON.parse(value);
                        return Array.isArray(parsed) ? parsed : (parsed && typeof parsed === 'object' ? Object.values(parsed) : []);
                    } catch (_) {
                        return [];
                    }
                }
                return [];
            };
            const value = item => esc(item === null || item === undefined || String(item).trim() === '' ? 'S/I' : item);
            const check = selected => selected ? '<i class="ti ti-circle-check text-success fs-5" aria-label="Seleccionado"></i>' : '';
            const tableSection = (title, headers, rows, columns = headers.length, emptyMessage = 'No se encontró información para mostrar.') =>
                '<div class="col-12 mb-3"><div class="table-responsive"><table class="table table-striped table-bordered mb-0 align-middle">' +
                '<thead>' + (title ? '<tr><th class="text-center align-middle" colspan="' + columns + '">' + title + '</th></tr>' : '') +
                (asList(headers).length ? '<tr>' + asList(headers).map(header => '<th class="text-center align-middle">' + header + '</th>').join('') + '</tr>' : '') +
                '</thead><tbody>' + (asList(rows).length ? asList(rows).join('') : '<tr><td colspan="' + columns + '" class="text-center text-primary">' + emptyMessage + '</td></tr>') +
                '</tbody></table></div></div>';
            const choiceTable = (title, labels, selected) => {
                labels = asList(labels);
                const selectedValue = Number(detail[selected]);
                const rows = labels.some((_, index) => selectedValue === index + 1)
                    ? ['<tr>' + labels.map((_, index) => '<td class="text-center">' + check(selectedValue === index + 1) + '</td>').join('') + '</tr>']
                    : [];
                return tableSection(title, labels, rows, labels.length, 'No se encontró información seleccionada.');
            };

            const field = (label, content, column = 'col-12 col-md-4') =>
                '<div class="' + column + ' mb-3"><label class="form-label mb-1">' + label + '</label><div class="text-break">' + content + '</div></div>';
            const firmaRegistrada = tipo => asList(detail.firmas).some(signature => signature?.tipo === tipo);
            const estadoCompletado = ['Refacción Instalada', 'Orden cerrada'].includes(detail.estatus_texto);
            const statusClass = Number(detail.estatus) === 0 ? 'bg-danger text-white' : Number(detail.estatus) === 1 ? 'bg-warning text-white' : (estadoCompletado ? 'bg-success text-white' : 'bg-info text-white');
            let estadoFirma = '';
            if (Number(detail.estatus) === 0) estadoFirma = 'La orden está pendiente de finalizar y asi continuar con el proceso de firmas.';
            else if (Number(detail.estatus) === 1 && !firmaRegistrada('B')) estadoFirma = '<i class="ti ti-signature me-1"></i> Hace falta la firma de Visto Bueno (VO.BO).';
            else if (Number(detail.estatus) === 1 && !firmaRegistrada('C')) estadoFirma = '<i class="ti ti-signature me-1"></i> Hace falta la firma de autorización.';
            else if (Number(detail.estatus) >= 2 && !estadoCompletado) estadoFirma = 'Orden autorizada; queda pendiente registrar la instalación de la refacción.';
            else if (estadoCompletado) estadoFirma = '<i class="ti ti-circle-check me-1"></i> ' + value(detail.estatus_texto);
            let html = '<div class="col-12 mb-3 text-end"><strong>Estatus:</strong> <span class="badge ' + statusClass + '">' + value(detail.estatus_texto) + '</span></div>' +
                '<div class="col-12 mb-2"><div class="alert text-center ' + (Number(detail.estatus) === 0 ? 'alert-warning' : Number(detail.estatus) === 1 ? 'alert-warning' : estadoCompletado ? 'alert-success' : 'alert-info') + '" role="status">' + estadoFirma + '</div></div>' +
                '<div class="col-12"><div class="row g-3">' +
                field('Folio:', value(detail.folio_label)) +
                field('Fecha:', value(dateLabel(detail.fecha_label, detail.fecha))) +
                field('Estación:', value(detail.estacion)) +
                '</div></div>';
            const affectationTitle = detail.id_estacion === 9
                ? '¿En qué afecta al autolavado?'
                : '¿En qué afecta a la estación?';
            const affectation = String(detail.afectacion ?? '').trim();
            html += tableSection(
                affectationTitle,
                [],
                affectation && affectation !== 'S/I'
                    ? ['<tr><td class="text-center text-break">' + value(affectation) + '</td></tr>']
                    : [],
                1,
                'No se encontró información seleccionada.'
            );
            html += choiceTable('Tipo de Servicio', ['Preventivo', 'Correctivo', 'Emergente'], 'tipo_servicio');
            html += choiceTable('¿La orden de trabajo se puede atender internamente?', ['Si', 'No', 'Ambas'], 'orden_trabajo');
            html += choiceTable('¿La orden de trabajo es de alto riesgo?', ['Si', 'No'], 'orden_riesgo');

            if (detail.id_estacion !== 9) {
                const areaRows = asList(detail.areas).map(area => {
                    const activeSubareas = asList(area?.subareas).map(subarea => subarea?.nombre).filter(Boolean);
                    const subareaText = activeSubareas.length ? ' <span class="text-muted">(' + activeSubareas.map(esc).join('), (') + ')</span>' : '';
                    return '<tr><td class="align-middle">' + value(area.area) + subareaText + '</td><td class="text-center align-middle" style="width:56px">' + check(Number(area.estatus) === 1) + '</td></tr>';
                });
                html += tableSection('Área', [], areaRows, 2);
            }

            const materialRows = asList(detail.materiales).map(material =>
                '<tr><td class="align-middle">' + value(material.concepto) + '</td><td class="text-center align-middle">' + value(material.cantidad) + '</td><td class="text-center align-middle">' + value(material.nota) + '</td></tr>'
            );
            html += tableSection('Refacciones', ['Refacción', 'Cantidad', 'Estatus'], materialRows, 3);

            const evidenceRows = asList(detail.evidencias).map(file =>
                '<tr><td class="text-center align-middle"><a class="text-primary" href="' + esc(file.url) + '" title="Descargar evidencia"><i class="ti ti-file-download fs-5"></i></a></td><td class="text-center align-middle">' + value(file.area) + '</td><td class="align-middle text-break">' + value(file.motivo) + '</td></tr>'
            );
            html += tableSection('Evidencia', ['Archivo', 'Área', 'Motivo'], evidenceRows, 3);
            html += tableSection('Comentarios', [], [
                '<tr><td class="text-center text-break">' + value(detail.comentarios_generales) + '</td></tr>'
            ], 1);

            const firmas = asList(detail.firmas);
            const getFirma = type => firmas.find(signature => signature.tipo === type) || null;
            const firmaCard = (type, missingTitle, missingMessage) => {
                const signature = getFirma(type);
                const title = signature?.titulo || missingTitle;
                const headerIcon = signature ? 'ti-user-check' : 'ti-clock-hour-4';
                let body = '';
                let footer = '';
                if (!signature) {
                    body = '<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i><h6 class="text-muted mb-0">' + missingMessage + '</h6>';
                    footer = '<small class="text-muted">Pendiente de firma electrónica</small>';
                } else if (type === 'A' && signature.firma_url) {
                    body = '<img src="' + esc(signature.firma_url) + '" alt="Firma de ' + value(signature.usuario) + '" class="img-fluid" style="max-height:90px;object-fit:contain">';
                    footer = '<h6 class="mb-0 fw-semibold text-truncate">' + value(signature.usuario) + '</h6>';
                } else if (type === 'A') {
                    body = '<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i><h6 class="text-muted mb-0">La firma está registrada, pero su imagen no está disponible.</h6>';
                    footer = '<h6 class="mb-0 fw-semibold text-truncate">' + value(signature.usuario) + '</h6>';
                } else {
                    body = '<i class="ti ti-signature text-primary mb-3" style="font-size:70px"></i><small class="text-dark"><strong>Fecha: ' + value(signatureDateLabel(signature)) + '</strong><br>La solicitud se firmó por un medio electrónico.</small>';
                    footer = '<h6 class="mb-0 fw-semibold text-truncate">' + value(signature.usuario) + '</h6>';
                }
                return '<div class="col-xl-4 col-lg-6 col-md-6 mb-3"><div class="card border h-100">' +
                    '<div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center">' +
                    '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px"><i class="ti ' + headerIcon + ' fs-5"></i></div>' +
                    '<div class="ms-3 overflow-hidden"><h6 class="mb-0 text-white">' + value(title) + '</h6></div></div></div>' +
                    '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">' + body + '</div>' +
                    '<div class="card-footer bg-light text-center">' + footer + '</div></div></div>';
            };
            html += '<div class="col-12"><label class="form-label mb-3">Firmas:</label><div class="row">' +
                firmaCard('A', 'ELABORÓ / ENCARGADO', 'Sin firma registrada') +
                firmaCard('B', 'VO.BO.', '¡Falta la firma de VO.Bo.!') +
                firmaCard('C', 'AUTORIZACIÓN', '¡Falta la firma de Autorización!') +
                '</div></div>';

            document.getElementById('orden-detalle-contenido').innerHTML = html;
            document.getElementById('orden-detalle-contenido').querySelectorAll('img').forEach(image => {
                image.addEventListener('error', () => {
                    const body = image.closest('.card-body');
                    if (body) body.innerHTML = '<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i><h6 class="text-muted mb-0">La imagen de la firma no está disponible.</h6>';
                }, { once: true });
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-detalle-orden')).show();
        } catch (error) { Notify.error(error.response?.data?.message || error.message || 'No se pudo abrir el detalle.'); }
    };
    table.on('click', '.js-orden-detail', event => {
        event.preventDefault();
        const toggle = event.currentTarget.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        showDetail(event.currentTarget.dataset.id);
    });
    table.on('click', '.js-orden-evidence', event => {
        event.preventDefault();
        if (event.currentTarget.classList.contains('disabled')) return;
        const toggle = event.currentTarget.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        const row = $.fn.DataTable.isDataTable(table)
            ? table.DataTable().row(event.currentTarget.closest('tr')).data()
            : null;
        openEvidenceModal(event.currentTarget.dataset.id, row?.folio_label || '');
    });
    table.on('click', '.js-orden-cause', event => {
        event.preventDefault();
        const toggle = event.currentTarget.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        openCauseModal(event.currentTarget.dataset.id);
    });
    table.on('click', '.js-orden-edit', event => {
        event.preventDefault();
        if (event.currentTarget.classList.contains('disabled')) return;
        const toggle = event.currentTarget.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        location.href = base + '/' + event.currentTarget.closest('tr').querySelector('.js-orden-detail')?.dataset.id;
    });
    table.on('click', '.js-orden-delete:not(.disabled)', async event => {
        event.preventDefault();
        const row = event.currentTarget;
        const toggle = row.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        const data = window.Alpine?.$data(app);
        if (data?.deleteAction) {
            await data.deleteAction({ url: base + '/eliminar', id: row.dataset.id, name: row.dataset.name, table: tableSelector });
        }
    });

    document.addEventListener('change', async event => {
        const subarea = event.target.closest('.js-subarea-status');
        if (subarea && record) {
            subarea.disabled = true;
            try {
                const { data } = await axios.post(base + '/areas/subareas/actualizar', { id_subarea: subarea.dataset.id, estatus: subarea.checked ? 1 : 0 }, {
                    headers: { 'X-Portal3-Skip-Action-Loader': '1' }
                });
                if (!data.success) throw new Error(data.message || 'No se pudo actualizar la subárea.');
                const parent = (record.areas || []).find(area => Number(area.id) === Number(subarea.dataset.parentAreaId));
                const option = (parent?.subareas_disponibles || []).find(item => Number(item.id) === Number(subarea.dataset.id));
                if (option) option.estatus = subarea.checked ? 1 : 0;
                const summary = document.querySelector('.js-area-selected-subareas[data-area-id="' + subarea.dataset.parentAreaId + '"]');
                const selectedNames = parent && Number(parent.estatus) === 1
                    ? parent.subareas_disponibles.filter(item => Number(item.estatus) === 1).map(item => item.nombre)
                    : [];
                if (summary) {
                    summary.textContent = selectedNames.length ? '(' + selectedNames.join(', ') + ')' : '';
                    summary.hidden = selectedNames.length === 0;
                }
            } catch (error) {
                subarea.checked = !subarea.checked;
                Notify.error(error.response?.data?.message || error.message || 'No se pudo actualizar la subárea.');
            } finally { subarea.disabled = false; }
            return;
        }
        const input = event.target.closest('.js-area-status');
        if (!input || !record) return;
        input.disabled = true;
        try {
            const { data } = await axios.post(base + '/areas/actualizar', { id_area: input.dataset.id, estatus: input.checked ? 1 : 0 }, {
                headers: { 'X-Portal3-Skip-Action-Loader': '1' }
            });
            if (!data.success) throw new Error(data.message || 'No se pudo actualizar el área.');
            const area = (record.areas || []).find(item => Number(item.id) === Number(input.dataset.id));
            if (area) area.estatus = input.checked ? 1 : 0;
            const areaCell = input.closest('tr')?.querySelector('td:first-child');
            const optionsCell = input.closest('tr')?.querySelector('td:nth-child(2)');
            let optionsButton = optionsCell?.querySelector('.js-area-options');
            const summary = areaCell?.querySelector('.js-area-selected-subareas');
            const hasOptions = (area?.subareas_disponibles || []).length > 0;
            if (!input.checked) {
                optionsButton?.remove();
                if (summary) {
                    summary.textContent = '';
                    summary.hidden = true;
                }
            } else if (!optionsButton && hasOptions && optionsCell) {
                optionsButton = document.createElement('button');
                optionsButton.type = 'button';
                optionsButton.className = 'btn btn-link btn-sm p-0 text-decoration-none js-area-options';
                optionsButton.dataset.id = String(input.dataset.id);
                optionsButton.setAttribute('aria-label', 'Seleccionar opciones de ' + area.area);
                optionsButton.title = 'Opciones del área';
                optionsButton.innerHTML = '<i class="ti ti-list-details fs-6"></i>';
                optionsCell.querySelector('.d-flex')?.appendChild(optionsButton);
                const selectedNames = (area.subareas_disponibles || []).filter(item => Number(item.estatus) === 1).map(item => item.nombre);
                if (summary) {
                    summary.textContent = selectedNames.length ? '(' + selectedNames.join(', ') + ')' : '';
                    summary.hidden = selectedNames.length === 0;
                }
            }
            if (input.checked) openAreaOptions(input.dataset.id);
        } catch (error) {
            input.checked = !input.checked;
            Notify.error(error.response?.data?.message || error.message || 'No se pudo actualizar el área.');
        } finally { input.disabled = false; }
    });

    document.getElementById('orden-areas')?.addEventListener('click', event => {
        const button = event.target.closest('.js-area-options');
        if (!button || button.disabled) return;
        event.preventDefault();
        openAreaOptions(button.dataset.id);
    });

    document.getElementById('orden-guardar-area')?.addEventListener('click', async event => {
        if (!record) return;
        const button = event.currentTarget;
        const field = document.getElementById('orden-nombre-area');
        const name = field.value.trim();
        if (!name) return Notify.error('Escribe el nombre del área.');
        button.disabled = true;
        try {
            const { data } = await axios.post(base + '/areas/agregar', { id: record.id, area: name });
            if (!data.success) throw new Error(data.message || 'No se pudo agregar el área.');
            bootstrap.Modal.getInstance(document.getElementById('modal-area-orden'))?.hide();
            field.value = '';
            location.reload();
        } catch (error) { Notify.error(error.response?.data?.message || error.message || 'No se pudo agregar el área.'); }
        finally { button.disabled = false; }
    });
    document.getElementById('orden-agregar-area')?.addEventListener('click', () => {
        document.getElementById('orden-nombre-area').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-area-orden')).show();
        window.setTimeout(() => document.getElementById('orden-nombre-area').focus(), 150);
    });

    document.getElementById('orden-agregar-refaccion')?.addEventListener('click', async () => {
        if (!record) return;
        try {
            const { data } = await axios.post(base + '/refacciones', { id: record.id });
            if (!data.success) throw new Error(data.message || 'No se pudo cargar el catálogo.');
            const select = document.getElementById('orden-refaccion');
            select.innerHTML = '<option value="">Otra...</option>' + (data.data || []).map(item => '<option value="' + item.id + '">' + esc(item.nombre + ' (' + item.estado + ')') + '</option>').join('');
            document.getElementById('orden-otra-refaccion').value = '';
            document.getElementById('orden-cantidad-refaccion').value = '';
            const $select = $(select);
            if ($.fn.select2 && !$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    width: '100%',
                    language: 'es',
                    dropdownParent: $('#modal-refaccion-orden')
                });
            }
            $select.val('').trigger('change');
            document.getElementById('orden-otra-refaccion').classList.remove('is-invalid');
            document.getElementById('orden-cantidad-refaccion').classList.remove('is-invalid');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-refaccion-orden')).show();
        } catch (error) { Notify.error(error.response?.data?.message || error.message || 'No se pudo cargar el catálogo.'); }
    });

    const refaccionSelect = document.getElementById('orden-refaccion');
    const otraRefaccionInput = document.getElementById('orden-otra-refaccion');
    const refaccionSelectWrapper = refaccionSelect?.closest('.select2-modal-field');
    const syncOtraRefaccion = () => {
        const seleccionCatalogo = Boolean(refaccionSelect?.value);
        const capturaManual = Boolean(otraRefaccionInput?.value.trim());
        if (otraRefaccionInput) {
            otraRefaccionInput.disabled = seleccionCatalogo;
            if (seleccionCatalogo) otraRefaccionInput.value = '';
            otraRefaccionInput.classList.remove('is-invalid');
        }
        if (refaccionSelect) {
            refaccionSelect.disabled = !seleccionCatalogo && capturaManual;
            $(refaccionSelect).prop('disabled', refaccionSelect.disabled).trigger('change.select2');
        }
        refaccionSelectWrapper?.classList.remove('is-invalid');
    };
    if (refaccionSelect) {
        $(refaccionSelect).on('change select2:select select2:clear', syncOtraRefaccion);
    }
    otraRefaccionInput?.addEventListener('input', () => {
        otraRefaccionInput.classList.remove('is-invalid');
        syncOtraRefaccion();
    });
    document.getElementById('orden-cantidad-refaccion')?.addEventListener('input', event => event.currentTarget.classList.remove('is-invalid'));

    document.getElementById('orden-guardar-refaccion')?.addEventListener('click', async () => {
        const button = document.getElementById('orden-guardar-refaccion');
        const quantityInput = document.getElementById('orden-cantidad-refaccion');
        const hasCatalogMaterial = Boolean(refaccionSelect.value);
        const hasCustomMaterial = !hasCatalogMaterial && Boolean(otraRefaccionInput.value.trim());
        const quantity = Number(quantityInput.value);
        if (!hasCatalogMaterial && !hasCustomMaterial) {
            refaccionSelectWrapper?.classList.add('is-invalid');
            otraRefaccionInput.classList.add('is-invalid');
            Notify.error('Selecciona una refacción del catálogo o escribe otra.');
            return;
        }
        refaccionSelectWrapper?.classList.remove('is-invalid');
        otraRefaccionInput.classList.remove('is-invalid');
        if (!Number.isFinite(quantity) || quantity < 1) {
            quantityInput.classList.add('is-invalid');
            Notify.error('La cantidad debe ser de al menos 1.');
            return;
        }
        button.disabled = true;
        try {
            const { data } = await axios.post(base + '/refacciones/agregar', {
                id: record.id,
                refaccion: refaccionSelect.value,
                otro: otraRefaccionInput.value,
                cantidad: quantityInput.value
            });
            if (!data.success) throw new Error(data.message || 'No se pudo agregar la refacción.');
            bootstrap.Modal.getInstance(document.getElementById('modal-refaccion-orden')).hide();
            location.reload();
        } catch (error) { Notify.error(error.response?.data?.message || error.message || 'No se pudo agregar la refacción.'); }
        finally { button.disabled = false; }
    });

    document.getElementById('orden-materiales')?.addEventListener('click', async event => {
        const button = event.target.closest('.js-material-delete:not(.disabled)');
        if (!button) return;
        const globalActions = window.Alpine?.$data(app);
        if (!globalActions?.deleteAction) return;
        const response = await globalActions.deleteAction({ url: base + '/refacciones/eliminar', id: button.dataset.id, name: button.dataset.name, table: null });
        if (response?.success) location.reload();
    });

    const postUpload = async (url, formData) => {
        const { data } = await axios.post(url, formData);
        if (!data.success) throw new Error(data.message || 'No se pudo guardar el archivo.');
        await loadFiles();
    };
    document.getElementById('orden-modal-evidencia-agregar')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const file = document.getElementById('orden-modal-evidencia-archivo').files[0];
        if (!activeEvidenceId || !file) return Notify.error('Selecciona el archivo de evidencia.');
        const form = new FormData();
        form.append('id', activeEvidenceId);
        form.append('archivo', file);
        try {
            const saved = await withEvidenceLoader(button, 'Guardando...', async () => {
                await postUpload(base + '/instalacion/agregar', form);
                document.getElementById('orden-modal-evidencia-archivo').value = '';
                reloadTable();
                await loadEvidenceModalFiles(activeEvidenceId);
                return true;
            });
            if (saved) Notify.success('Evidencia agregada.');
        } catch (error) {
            Notify.error(error.response?.data?.message || error.message || 'No se pudo agregar la evidencia.');
        }
    });
    document.getElementById('orden-guardar-instalacion')?.addEventListener('click', async event => {
        const file = document.getElementById('orden-archivo-instalacion').files[0];
        if (!file || !record) return Notify.error('Selecciona el archivo de instalación.');
        const form = new FormData(); form.append('id', record.id); form.append('archivo', file);
        event.currentTarget.disabled = true;
        try { await postUpload(base + '/instalacion/agregar', form); Notify.success('Evidencia guardada.'); }
        catch (error) { Notify.error(error.response?.data?.message || error.message); }
        finally { event.currentTarget.disabled = false; }
    });
    document.getElementById('orden-guardar-evidencia')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const file = document.getElementById('orden-archivo-evidencia').files[0];
        const areaField = document.getElementById('orden-area-evidencia');
        const motivoField = document.getElementById('orden-motivo-evidencia');
        const area = areaField.value.trim();
        const motivo = motivoField.value.trim();
        const fileField = document.getElementById('orden-archivo-evidencia');
        fileField.classList.toggle('is-invalid', !file);
        areaField.classList.toggle('is-invalid', !area);
        motivoField.classList.toggle('is-invalid', !motivo);
        if (!file || !area || !motivo || !record) return Notify.error('Completa archivo, área y motivo.');
        const form = new FormData(); form.append('id', record.id); form.append('archivo', file); form.append('area', area); form.append('motivo', motivo);
        button.disabled = true;
        const original = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
        try {
            await postUpload(base + '/evidencias/agregar', form);
            bootstrap.Modal.getInstance(document.getElementById('modal-agregar-evidencia-orden'))?.hide();
            fileField.value = ''; areaField.value = ''; motivoField.value = '';
            [fileField, areaField, motivoField].forEach(field => field.classList.remove('is-invalid'));
            Notify.success('Evidencia agregada.');
        }
        catch (error) { Notify.error(error.response?.data?.message || error.message); }
        finally { button.disabled = false; button.innerHTML = original; }
    });
    document.getElementById('orden-agregar-evidencia')?.addEventListener('click', () => {
        ['orden-archivo-evidencia', 'orden-area-evidencia', 'orden-motivo-evidencia'].forEach(id => {
            const field = document.getElementById(id);
            field.value = '';
            field.classList.remove('is-invalid');
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-agregar-evidencia-orden')).show();
    });
    document.getElementById('orden-guardar-causa')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const descriptionField = document.getElementById('orden-descripcion-causa');
        const localityField = document.getElementById('orden-refaccion-causa');
        const priceField = document.getElementById('orden-precio-causa');
        const description = descriptionField.value.trim();
        const locality = localityField.value.trim();
        descriptionField.classList.toggle('is-invalid', !description);
        localityField.classList.toggle('is-invalid', !locality);
        if (!description || !locality) return Notify.error('Completa la descripción de la causa y dónde se queda la refacción.');
        if (!activeCauseId) return Notify.error('No se pudo identificar la orden de mantenimiento.');
        const form = new FormData();
        form.append('id', activeCauseId);
        form.append('descripcion', description);
        form.append('refaccion', locality);
        form.append('precio', priceField.value);
        const pdf = document.getElementById('orden-pdf-causa').files[0];
        if (pdf) form.append('factura_pdf', pdf);
        try {
            const saved = await withCauseLoader(button, 'Agregando...', async () => {
                await postUpload(base + '/causas/agregar', form);
                await loadCauseModalFiles(activeCauseId);
                reloadTable();
                ['orden-descripcion-causa', 'orden-refaccion-causa', 'orden-precio-causa', 'orden-pdf-causa'].forEach(id => {
                    const field = document.getElementById(id);
                    field.value = '';
                    field.classList.remove('is-invalid');
                });
                return true;
            });
            if (saved) Notify.success('Causa registrada.');
        } catch (error) {
            Notify.error(error.response?.data?.message || error.message || 'No se pudo registrar la causa.');
        }
    });

    const runGlobalDelete = async (button, url) => {
        const globalActions = window.Alpine?.$data(app);
        if (!globalActions?.deleteAction) return;
        const response = await globalActions.deleteAction({ url, id: button.dataset.id, name: button.dataset.name || 'archivo', table: null });
        if (response?.success) await loadFiles();
    };
    document.getElementById('orden-lista-evidencias')?.addEventListener('click', event => {
        const button = event.target.closest('.js-evidence-delete');
        if (button && !button.disabled) runGlobalDelete(button, base + '/evidencias/eliminar');
    });
    document.getElementById('orden-modal-evidencia-lista')?.addEventListener('click', async event => {
        const button = event.target.closest('.js-orden-evidence-delete');
        if (!button || !canEdit || evidenceBusy) return;
        const globalActions = window.Alpine?.$data(app);
        const confirmation = await Swal.fire({
            title: '¿Eliminar evidencia?',
            text: 'El archivo "' + (button.dataset.name || 'evidencia') + '" será eliminado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        });
        if (!confirmation.isConfirmed) return;

        try {
            const response = await withEvidenceLoader(button, 'Eliminando...', async () => {
                const response = await axios.post(base + '/instalacion/eliminar', { id: button.dataset.id });
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo eliminar la evidencia.');
                reloadTable();
                await loadEvidenceModalFiles(activeEvidenceId);
                return response;
            });
            if (response?.data?.success) {
                if (globalActions?.handleResponse) globalActions.handleResponse(response, null);
                else Notify.success(response.data.message || 'Evidencia eliminada.');
            }
        } catch (error) {
            const message = error.response?.data?.message || error.message || 'No se pudo eliminar la evidencia.';
            if (globalActions?.showAlert) globalActions.showAlert('error', 'Error', message);
            if (globalActions?.notify) globalActions.notify('error', message);
            else Notify.error(message);
        }
    });
    document.getElementById('orden-modal-causa-lista')?.addEventListener('click', async event => {
        const button = event.target.closest('.js-cause-delete');
        if (!button || button.disabled || !canEdit || causeBusy) return;
        const globalActions = window.Alpine?.$data(app);
        if (!globalActions?.deleteAction) return;
        const response = await globalActions.deleteAction({ url: base + '/causas/eliminar', id: button.dataset.id, name: button.dataset.name || 'Causa', table: null });
        if (response?.success) {
            reloadTable();
            await loadCauseModalFiles(activeCauseId);
        }
    });

    const canvas = document.getElementById('orden-canvas-firma');
    const signaturePad = canvas && typeof SignaturePad !== 'undefined'
        ? new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)', penColor: '#202124' })
        : null;
    const resizeSignatureCanvas = () => {
        if (!canvas || !signaturePad) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const bounds = canvas.getBoundingClientRect();
        if (!bounds.width || !bounds.height) return;
        canvas.width = bounds.width * ratio;
        canvas.height = bounds.height * ratio;
        const context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        signaturePad.clear();
    };
    resizeSignatureCanvas();
    window.addEventListener('resize', resizeSignatureCanvas);
    document.querySelectorAll('.js-orden-choice').forEach(input => input.addEventListener('change', () => {
        const fieldId = input.name === 'orden-tipo-radio' ? 'orden-tipo' : (input.name === 'orden-trabajo-radio' ? 'orden-trabajo' : 'orden-riesgo');
        document.getElementById(fieldId).value = input.value;
    }));
    document.getElementById('orden-limpiar-firma')?.addEventListener('click', () => signaturePad?.clear());
    document.getElementById('orden-finalizar')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const afectacion = document.getElementById('orden-afectacion').value.trim();
        if (!afectacion) {
            const field = document.getElementById('orden-afectacion');
            field.classList.add('is-invalid');
            field.focus();
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return Notify.error('Completa el campo de afectación antes de finalizar.');
        }
        const requiredGroups = [
            { name: 'orden-tipo-radio', label: 'Tipo de servicio' },
            { name: 'orden-trabajo-radio', label: 'Atención interna' },
            { name: 'orden-riesgo-radio', label: 'Orden de alto riesgo' }
        ];
        const missingGroup = requiredGroups.find(group => !document.querySelector('input[name="' + group.name + '"]:checked'));
        if (missingGroup) {
            const firstOption = document.querySelector('input[name="' + missingGroup.name + '"]');
            firstOption?.closest('table')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return Notify.error('Selecciona una opción en cada tabla. Falta: ' + missingGroup.label + '.');
        }
        if (!record || !signaturePad || signaturePad.isEmpty()) return Notify.error('Agrega tu firma para finalizar.');
        button.disabled = true;
        try {
            ['afectacion', 'comentarios'].forEach(field => window.clearTimeout(serviceSaveTimers[field]));
            await serviceSaveQueue.catch(() => undefined);
            const { data } = await axios.post(base + '/finalizar', {
                id: record.id,
                firma: signaturePad.toDataURL('image/png'),
                afectacion,
                comentarios: document.getElementById('orden-comentarios').value,
                tipo_servicio: document.querySelector('input[name="orden-tipo-radio"]:checked')?.value,
                orden_trabajo: document.querySelector('input[name="orden-trabajo-radio"]:checked')?.value,
                orden_riesgo: document.querySelector('input[name="orden-riesgo-radio"]:checked')?.value
            });
            if (!data.success) throw new Error(data.message || 'No se pudo finalizar la orden.');
            location.href = base;
        } catch (error) { Notify.error(error.response?.data?.message || error.message); }
        finally { button.disabled = false; }
    });
});
