document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('container');
    const puedeAdministrar = container?.dataset.puedeAdministrar === 'true';
    const adminAreas = container?.querySelectorAll('.seguro-admin-only') || [];
    const soloLecturaAreas = container?.querySelectorAll('.seguro-solo-lectura') || [];

    adminAreas.forEach((element) => element.classList.toggle('d-none', !puedeAdministrar));
    soloLecturaAreas.forEach((element) => element.classList.toggle('d-none', puedeAdministrar));
    container?.querySelectorAll('.seguro-card-body').forEach((element) => element.classList.toggle('p-0', !puedeAdministrar));

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[caracter]);
    const mostrar = (elemento, visible, claseVisible = '') => {
        if (claseVisible) elemento.classList.toggle(claseVisible, visible);
        elemento.classList.toggle('d-none', !visible);
    };

    const cargarVistaVigente = async ({ endpoint, frameId, downloadId, footerId, emptyId, titulo }) => {
        const frame = document.getElementById(frameId);
        const download = document.getElementById(downloadId);
        const footer = document.getElementById(footerId);
        const empty = document.getElementById(emptyId);
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('No fue posible cargar el documento vigente.');
            const result = await response.json();
            const registros = Array.isArray(result.data) ? result.data : [];
            const disponible = (registro) => registro.archivo && registro.archivo_disponible;
            const vigente = registros.find((registro) => Number(registro.estatus) === 0 && disponible(registro));
            const anterior = registros.find((registro) => Number(registro.estatus) === 1 && disponible(registro));
            const documento = vigente || anterior;

            if (!documento) {
                mostrar(frame, false);
                mostrar(download, false);
                mostrar(footer, false);
                mostrar(empty, true, 'd-flex');
                return;
            }

            const params = new URLSearchParams({ tipo: 'poliza-seguro', file: documento.archivo });
            frame.src = `/download?${params.toString()}&view=1`;
            mostrar(frame, true);
            download.href = `/download?${params.toString()}`;
            mostrar(download, true);
            mostrar(footer, true);
            mostrar(empty, false, 'd-flex');
            download.setAttribute('aria-label', `Descargar ${titulo} vigente`);
        } catch (error) {
            mostrar(frame, false);
            mostrar(download, false);
            mostrar(footer, false);
            mostrar(empty, true, 'd-flex');
            empty.querySelector('.empty-message').textContent = error.message || 'No fue posible cargar el documento vigente.';
        }
    };

    if (!puedeAdministrar) {
        cargarVistaVigente({
            endpoint: '/seguro/poliza-seguro/datatable',
            frameId: 'preview-poliza',
            downloadId: 'download-poliza',
            footerId: 'footer-download-poliza',
            emptyId: 'sin-poliza-vigente',
            titulo: 'la póliza de seguro',
        });
        cargarVistaVigente({
            endpoint: '/seguro/poliza-seguro-cobertura/datatable',
            frameId: 'preview-cobertura',
            downloadId: 'download-cobertura',
            footerId: 'footer-download-cobertura',
            emptyId: 'sin-cobertura-vigente',
            titulo: 'la cobertura de la póliza',
        });
        return;
    }

    const renderEstatus = (data, type) => {
        if (type !== 'display') return data;
        const values = {
            0: ['success', 'Vigente'],
            1: ['warning', 'No vigente'],
            2: ['danger', 'Eliminado'],
        };
        const [clase, texto] = values[Number(data)] || ['secondary', 'Desconocido'];
        return `<span class="badge rounded-pill bg-${clase}">${texto}</span>`;
    };

    const renderAcciones = (row, tabla, endpointDelete) => {
        const archivoDisponible = row.archivo_disponible && Number(row.estatus) !== 2;
        const puedeEliminar = Number(row.estatus) !== 2;
        const extension = String(row.archivo || '').split('.').pop().toLowerCase();
        const puedePrevisualizar = archivoDisponible && ['pdf', 'jpg', 'jpeg', 'png'].includes(extension);
        const opciones = [
            `<li><a class="dropdown-item ${puedePrevisualizar ? 'pointer btn-seguro-preview' : 'disabled'}"
                ${puedePrevisualizar ? `data-file="${escapar(row.archivo)}" data-title="Documento de Seguro"` : 'aria-disabled="true" tabindex="-1"'}>
                <i class="ti ti-eye fs-5 me-1"></i>Detalle</a></li>`,
            `<li><a class="dropdown-item ${archivoDisponible ? 'pointer' : 'disabled'}"
                ${archivoDisponible ? `@click="download('poliza-seguro', $el.dataset.file)" data-file="${escapar(row.archivo)}"` : 'aria-disabled="true" tabindex="-1"'}>
                <i class="ti ti-file-download fs-5 me-1"></i>Descargar</a></li>`,
            `<li><a class="dropdown-item ${puedeEliminar ? 'pointer' : 'disabled'}"
                ${puedeEliminar ? `@click="deleteAction({ url: '${endpointDelete}', id: ${Number(row.id)}, name: '${Number(row.id)}', table: '${tabla}' })"` : 'aria-disabled="true" tabindex="-1"'}>
                <i class="ti ti-trash fs-5 me-1"></i>Eliminar</a></li>`,
        ];

        return `<div x-data="actions()" class="d-flex justify-content-center">
            <div class="dropdown dropstart">
                <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones"><i class="ti ti-dots-vertical fs-6"></i></a>
                <ul class="dropdown-menu">${opciones.join('')}</ul>
            </div>
        </div>`;
    };

    const crearTabla = (selector, endpoint, endpointDelete) => {
        if (!document.querySelector(selector) || $.fn.DataTable.isDataTable(selector)) return;
        $(selector).DataTable({
            processing: true,
            serverSide: false,
            autoWidth: false,
            stateSave: true,
            order: [[0, 'desc']],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json',
                emptyTable: 'No se encontró información',
                zeroRecords: 'No se encontró información',
            },
            ajax: {
                url: endpoint,
                type: 'GET',
                dataSrc: (json) => Array.isArray(json.data) ? json.data : [],
            },
            columns: [
                { data: 'id', width: '60px', className: 'text-center align-middle' },
                {
                    data: 'fecha_hora',
                    className: 'align-middle',
                    render: (data, type, row) => type === 'display' ? escapar(row.fecha_hora_formateada || data || '') : data,
                },
                { data: 'estatus', width: '140px', className: 'text-center align-middle', render: renderEstatus },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    width: '48px',
                    className: 'text-center align-middle',
                    render: (_data, type, row) => type === 'display' ? renderAcciones(row, selector, endpointDelete) : '',
                },
            ],
        });
    };

    crearTabla('#table-poliza', '/seguro/poliza-seguro/datatable', '/seguro/delete-poliza-seguro');
    crearTabla('#table-poliza-cobertura', '/seguro/poliza-seguro-cobertura/datatable', '/seguro/delete-poliza-seguro-cobertura');

    document.addEventListener('click', (event) => {
        const preview = event.target.closest('.btn-seguro-preview');
        if (!preview) return;

        const file = preview.dataset.file;
        const params = new URLSearchParams({ tipo: 'poliza-seguro', file, view: '1' });
        const modal = document.getElementById('modal-seguro-preview');
        document.getElementById('modal-seguro-preview-title').textContent = preview.dataset.title || 'Documento de Seguro';
        document.getElementById('seguro-preview-frame').src = `/download?${params.toString()}`;
        document.getElementById('seguro-preview-download').href = `/download?${new URLSearchParams({ tipo: 'poliza-seguro', file }).toString()}`;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    document.getElementById('modal-seguro-preview')?.addEventListener('hidden.bs.modal', () => {
        document.getElementById('seguro-preview-frame').src = 'about:blank';
    });
});
