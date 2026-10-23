document.addEventListener('alpine:init', () => {
    Alpine.data('procedimientosAdminComponent', () => ({
        erroresProcedimiento: { nombre_a: '', archivo: '' },
        modoProcedimiento: 'crear',
        tipoProcedimiento: 'actividades-tecnicas',
        tablaProcedimiento: '#table-actividades-tecnicas',
        modalProcedimiento: null,

        init() {
            const modal = document.getElementById('modal-procedimiento');
            if (modal) this.modalProcedimiento = bootstrap.Modal.getOrCreateInstance(modal);
        },

        abrirAgregar(tipo, tabla) {
            this.$refs.formProcedimiento.reset();
            this.tipoProcedimiento = tipo;
            this.tablaProcedimiento = tabla;
            this.modoProcedimiento = 'crear';
            this.erroresProcedimiento = { nombre_a: '', archivo: '' };
            this.$refs.procedimientoTipo.value = tipo;
            this.$refs.procedimientoId.value = '';
            this.modalProcedimiento?.show();
        },

        editarProcedimiento({ tipo, id, nombre, tabla }) {
            this.$refs.formProcedimiento.reset();
            this.tipoProcedimiento = tipo;
            this.tablaProcedimiento = tabla;
            this.modoProcedimiento = 'editar';
            this.erroresProcedimiento = { nombre_a: '', archivo: '' };
            this.$refs.procedimientoTipo.value = tipo;
            this.$refs.procedimientoId.value = id;
            this.$refs.procedimientoNombre.value = nombre;
            this.modalProcedimiento?.show();
        },

        validarProcedimiento() {
            this.erroresProcedimiento = { nombre_a: '', archivo: '' };
            const nombre = this.$refs.procedimientoNombre.value.trim();
            const archivo = this.$refs.procedimientoArchivo.files?.[0] || null;

            if (!nombre) {
                this.erroresProcedimiento.nombre_a = 'El nombre es obligatorio.';
            }

            if (this.modoProcedimiento === 'crear' && !archivo) {
                this.erroresProcedimiento.archivo = 'Debe seleccionar un archivo PDF.';
            } else if (archivo) {
                const extensionPdf = archivo.name.toLowerCase().endsWith('.pdf');
                const tipoPdf = !archivo.type || archivo.type === 'application/pdf';
                if (!extensionPdf || !tipoPdf) {
                    this.erroresProcedimiento.archivo = 'Solo se permiten archivos PDF.';
                } else if (archivo.size <= 0 || archivo.size > 25 * 1024 * 1024) {
                    this.erroresProcedimiento.archivo = 'El archivo debe pesar más de 0 y no exceder 25 MB.';
                }
            }

            const invalido = Object.values(this.erroresProcedimiento).some(Boolean);
            if (invalido) this.notify('error', 'Completa correctamente los campos obligatorios.');
            return !invalido;
        },

        async guardarProcedimiento() {
            if (!this.validarProcedimiento()) return;

            const endpoint = this.modoProcedimiento === 'crear'
                ? '/procedimientos/create'
                : '/procedimientos/update';

            window.loader?.show();
            try {
                const respuesta = await this.createAction({
                    url: endpoint,
                    data: new FormData(this.$refs.formProcedimiento),
                    method: 'POST',
                    table: this.tablaProcedimiento,
                    onSuccess: () => {
                        this.modalProcedimiento?.hide();
                        this.$refs.formProcedimiento.reset();
                    },
                });

                if (!respuesta?.success && respuesta?.errors) {
                    this.erroresProcedimiento = {
                        nombre_a: respuesta.errors.nombre_a || '',
                        archivo: respuesta.errors.archivo || '',
                    };
                }
            } finally {
                window.loader?.hide();
            }
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const puedeAdministrar = document.getElementById('container')?.dataset.puedeAdministrar === 'true';
    const permisos = {
        'actividades-tecnicas': { administrar: puedeAdministrar, descargar: true },
        'visita-estacion': { administrar: puedeAdministrar, descargar: true },
    };

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[caracter]);

    const actualizarPermisos = (tipo, recibidos) => {
        permisos[tipo] = { ...permisos[tipo], ...(recibidos || {}) };
        document.querySelectorAll(`.btn-procedimientos-agregar[data-tipo="${tipo}"]`).forEach((boton) => {
            boton.classList.toggle('d-none', !permisos[tipo].administrar);
        });
    };

    const acciones = (tipo, idField, tableSelector) => ({
        title: puedeAdministrar
            ? '<i class="ti ti-dots-vertical fs-6"></i>'
            : '<i class="ti ti-download fs-6 text-primary" aria-label="Descargar"></i>',
        data: null,
        orderable: false,
        searchable: false,
        width: '48px',
        className: 'text-center align-middle',
        render: (_data, _type, row) => {
            const archivoDisponible = row.archivo_disponible && row.archivo;
            if (!puedeAdministrar) {
                return `<div x-data="actions()" class="d-flex justify-content-center">
                    <a class="pointer ${archivoDisponible ? '' : 'text-muted'}"
                        ${archivoDisponible ? `@click="download($el.dataset.tipo, $el.dataset.archivo)" data-tipo="procedimientos-${tipo}" data-archivo="${escapar(row.archivo)}"` : 'aria-disabled="true"'}
                        aria-label="Descargar archivo" title="${archivoDisponible ? 'Descargar archivo' : 'Archivo no disponible'}">
                        <i class="ti ti-download text-primary fs-5"></i>
                    </a>
                </div>`;
            }

            let opciones = `
                <li><a class="dropdown-item pointer ${archivoDisponible ? '' : 'disabled'}"
                    ${archivoDisponible ? `@click="download($el.dataset.tipo, $el.dataset.archivo)" data-tipo="procedimientos-${tipo}" data-archivo="${escapar(row.archivo)}"` : 'aria-disabled="true"'}>
                    <i class="ti ti-file-download fs-5 me-1"></i>Descargar</a></li>`;

            if (permisos[tipo].administrar) {
                opciones += `
                    <li><a class="dropdown-item pointer btn-procedimiento-editar"
                        data-tipo="${tipo}" data-id="${Number(row[idField]) || 0}"
                        data-nombre="${escapar(row.nombre_a)}" data-table="${tableSelector}">
                        <i class="ti ti-edit fs-5 me-1"></i>Editar</a></li>
                    <li><a class="dropdown-item pointer btn-procedimiento-eliminar"
                        data-tipo="${tipo}" data-id="${Number(row[idField]) || 0}"
                        data-nombre="${escapar(row.nombre_a)}" data-table="${tableSelector}">
                        <i class="ti ti-trash fs-5 me-1"></i>Eliminar</a></li>`;
            }

            return `<div x-data="actions()" class="d-flex gap-1 justify-content-center">
                <div class="dropdown dropstart">
                    <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones"><i class="ti ti-dots-vertical fs-6"></i></a>
                    <ul class="dropdown-menu">${opciones}</ul>
                </div>
            </div>`;
        },
    });

    const columnas = (tipo, idField, title, selector) => [
        { title: '#', data: idField, width: '60px', className: 'text-center align-middle' },
        { title, data: 'nombre_a', className: 'text-center align-middle' },
        acciones(tipo, idField, selector),
    ];

    const configurarTabla = ({ selector, endpoint, tipo, idField, title }) => $(selector).DataTable({
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
            url: endpoint,
            type: 'GET',
            dataSrc: (json) => {
                actualizarPermisos(tipo, json.permisos);
                return json.data || [];
            },
        },
        columns: columnas(tipo, idField, title, selector),
    });

    const tablas = {
        actividades: configurarTabla({
            selector: '#table-actividades-tecnicas',
            endpoint: '/procedimientos/actividades-tecnicas/datatable',
            tipo: 'actividades-tecnicas',
            idField: 'id_actividades_tecnicas',
            title: 'Nombre de la actividad',
        }),
        visitas: configurarTabla({
            selector: '#table-visitas-estacion',
            endpoint: '/procedimientos/visita-estacion/datatable',
            tipo: 'visita-estacion',
            idField: 'id_visita_estacion',
            title: 'Nombre de la visita',
        }),
    };

    document.addEventListener('click', (event) => {
        const editar = event.target.closest('.btn-procedimiento-editar');
        if (editar) {
            window.Alpine?.$data(document.getElementById('container'))?.editarProcedimiento({
                tipo: editar.dataset.tipo,
                id: editar.dataset.id,
                nombre: editar.dataset.nombre,
                tabla: editar.dataset.table,
            });
            return;
        }

        const eliminar = event.target.closest('.btn-procedimiento-eliminar');
        if (eliminar) {
            const componente = window.Alpine?.$data(eliminar.closest('[x-data]'));
            componente?.deleteAction({
                url: '/procedimientos/delete',
                id: Number(eliminar.dataset.id),
                name: eliminar.dataset.nombre,
                table: eliminar.dataset.table,
                data: { tipo: eliminar.dataset.tipo },
            });
        }
    });

    window.procedimientosTablas = tablas;
});
