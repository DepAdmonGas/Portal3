document.addEventListener('alpine:init', () => {
    Alpine.data('empresaAdminComponent', () => ({
        tiposEmpresa: [],
        modoEmpresa: 'crear',
        erroresEmpresa: { descripcion: '', id_tipo: '', archivo: '' },
        modalEmpresa: null,

        init() {
            this.tiposEmpresa = window.empresaTipos || [];
            const modal = document.getElementById('modal-empresa-form');
            if (modal) this.modalEmpresa = bootstrap.Modal.getOrCreateInstance(modal);
        },

        abrirNuevo() {
            if (document.getElementById('container')?.dataset.puedeAdministrar !== 'true') return;
            this.$refs.formEmpresa.reset();
            this.modoEmpresa = 'crear';
            this.erroresEmpresa = { descripcion: '', id_tipo: '', archivo: '' };
            this.$refs.empresaId.value = '';
            this.modalEmpresa?.show();
        },

        editarEmpresa({ id, descripcion, idTipo }) {
            this.$refs.formEmpresa.reset();
            this.modoEmpresa = 'editar';
            this.erroresEmpresa = { descripcion: '', id_tipo: '', archivo: '' };
            this.$refs.empresaId.value = id;
            this.$refs.empresaDescripcion.value = descripcion;
            this.$refs.empresaTipo.value = idTipo;
            this.modalEmpresa?.show();
        },

        validarEmpresa() {
            this.erroresEmpresa = { descripcion: '', id_tipo: '', archivo: '' };
            const descripcion = this.$refs.empresaDescripcion.value.trim();
            const tipo = Number(this.$refs.empresaTipo.value);
            const archivo = this.$refs.empresaArchivo.files?.[0] || null;

            if (!descripcion) this.erroresEmpresa.descripcion = 'La descripción es obligatoria.';
            if (!tipo || !this.tiposEmpresa.some((item) => Number(item.id) === tipo)) {
                this.erroresEmpresa.id_tipo = 'Seleccione un tipo válido.';
            }
            if (this.modoEmpresa === 'crear' && !archivo) {
                this.erroresEmpresa.archivo = 'Debe seleccionar un archivo PDF.';
            } else if (archivo) {
                const esPdf = archivo.name.toLowerCase().endsWith('.pdf')
                    && (!archivo.type || archivo.type === 'application/pdf');
                if (!esPdf) {
                    this.erroresEmpresa.archivo = 'Solo se permiten archivos PDF.';
                } else if (archivo.size <= 0 || archivo.size > 25 * 1024 * 1024) {
                    this.erroresEmpresa.archivo = 'El archivo debe pesar más de 0 y no exceder 25 MB.';
                }
            }

            const invalido = Object.values(this.erroresEmpresa).some(Boolean);
            if (invalido) this.notify('error', 'Completa correctamente los campos obligatorios.');
            return !invalido;
        },

        async guardarEmpresa() {
            if (!this.validarEmpresa()) return;
            const editar = this.modoEmpresa === 'editar';
            await this.createAction({
                url: editar ? '/empresa/update' : '/empresa/create',
                data: new FormData(this.$refs.formEmpresa),
                method: 'POST',
                table: '#table-empresa',
                onSuccess: () => {
                    this.modalEmpresa?.hide();
                    this.$refs.formEmpresa.reset();
                },
                onError: (respuesta) => {
                    this.erroresEmpresa = {
                        descripcion: respuesta.errors?.descripcion || '',
                        id_tipo: respuesta.errors?.id_tipo || '',
                        archivo: respuesta.errors?.archivo || '',
                    };
                },
            });
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('container');
    const puedeAdministrar = container?.dataset.puedeAdministrar === 'true';

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[caracter]);

    const actualizarPermisos = (permisos = {}) => {
        document.querySelector('.btn-empresa-nuevo')?.classList.toggle('d-none', !permisos.administrar);
        const alpine = window.Alpine;
        if (alpine && container) alpine.$data(container).tiposEmpresa = window.empresaTipos || [];
    };

    const columnas = () => [
        { title: '#', data: 'id', width: '96px', className: 'text-center align-middle' },
        { title: 'Descripción', data: 'descripcion', className: 'align-middle' },
        { title: 'Tipo', data: 'tipo', className: 'text-center align-middle' },
        {
            title: puedeAdministrar
                ? '<i class="ti ti-dots-vertical fs-6"></i>'
                : '<i class="ti ti-download text-primary fs-6" aria-label="Descargar"></i>',
            data: null,
            orderable: false,
            searchable: false,
            width: '48px',
            className: 'text-center align-middle',
            render: (_data, _type, row) => {
                const disponible = Boolean(row.archivo_disponible && row.archivo);
                const descarga = disponible
                    ? `@click="download($el.dataset.tipo, $el.dataset.archivo)" data-tipo="empresa" data-archivo="${escapar(row.archivo)}"`
                    : 'aria-disabled="true"';

                if (!puedeAdministrar) {
                    return `<div x-data="actions()" class="d-flex justify-content-center">
                        <a class="pointer ${disponible ? '' : 'text-muted'}" ${descarga}
                            aria-label="Descargar archivo" title="${disponible ? 'Descargar archivo' : 'Archivo no disponible'}">
                            <i class="ti ti-download text-primary fs-5"></i>
                        </a>
                    </div>`;
                }

                return `<div x-data="actions()" class="d-flex justify-content-center">
                    <div class="dropdown dropstart">
                        <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones"><i class="ti ti-dots-vertical fs-6"></i></a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item pointer ${disponible ? 'btn-empresa-preview' : 'disabled'}"
                                data-file="${escapar(row.archivo)}" data-descripcion="${escapar(row.descripcion)}" ${disponible ? '' : 'aria-disabled="true"'}>
                                <i class="ti ti-eye fs-5 me-1"></i>Visualizar</a></li>
                            <li><a class="dropdown-item pointer ${disponible ? '' : 'disabled'}" ${descarga}>
                                <i class="ti ti-download fs-5 me-1"></i>Descargar</a></li>
                            <li><a class="dropdown-item pointer btn-empresa-editar" data-id="${Number(row.id) || 0}"
                                data-descripcion="${escapar(row.descripcion)}" data-id-tipo="${Number(row.id_tipo) || 0}">
                                <i class="ti ti-edit fs-5 me-1"></i>Editar</a></li>
                            <li><a class="dropdown-item pointer btn-empresa-eliminar" data-id="${Number(row.id) || 0}"
                                data-descripcion="${escapar(row.descripcion)}">
                                <i class="ti ti-trash fs-5 me-1"></i>Eliminar</a></li>
                        </ul>
                    </div>
                </div>`;
            },
        },
    ];

    const table = $('#table-empresa').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'asc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: 'No se encontro informacion',
            zeroRecords: 'No hay coincidencias para la búsqueda.',
            loadingRecords: 'Cargando información...',
            processing: 'Procesando...',
        },
        ajax: {
            url: '/empresa/datatable',
            type: 'GET',
            dataSrc: (json) => {
                window.empresaTipos = json.tipos || [];
                actualizarPermisos(json.permisos || {});
                return json.data || [];
            },
        },
        columns: columnas(),
    });

    document.addEventListener('click', (event) => {
        const editar = event.target.closest('.btn-empresa-editar');
        if (editar && container) {
            window.Alpine?.$data(container)?.editarEmpresa({
                id: editar.dataset.id,
                descripcion: editar.dataset.descripcion,
                idTipo: editar.dataset.idTipo,
            });
            return;
        }

        const eliminar = event.target.closest('.btn-empresa-eliminar');
        if (eliminar && container) {
            window.Alpine?.$data(container)?.deleteAction({
                url: '/empresa/delete',
                id: Number(eliminar.dataset.id),
                name: eliminar.dataset.descripcion,
                table: '#table-empresa',
            });
            return;
        }

        const preview = event.target.closest('.btn-empresa-preview');
        if (preview) {
            const file = preview.dataset.file;
            const params = new URLSearchParams({ tipo: 'empresa', file, view: '1' });
            document.getElementById('modal-empresa-preview-title').textContent = preview.dataset.descripcion;
            document.getElementById('empresa-preview-frame').src = `/download?${params.toString()}`;
            document.getElementById('empresa-preview-download').href = `/download?${new URLSearchParams({ tipo: 'empresa', file }).toString()}`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-empresa-preview')).show();
        }
    });

    document.getElementById('modal-empresa-preview')?.addEventListener('hidden.bs.modal', () => {
        document.getElementById('empresa-preview-frame').src = 'about:blank';
    });

    window.empresaTabla = table;
});
