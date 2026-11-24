document.addEventListener('alpine:init', () => {
    Alpine.data('evaluacionRequisitos', () => ({

        registros: [],
        loading: false,

        modalNuevo: null,
        fecha: '',
        documento: null,
        tabla: null,

        errors: {
            fecha: false,
            documento: false
        },

        init() {

            window.evaluacionRequisitosLegales = this;
            this.inicializarTabla();
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));

        },

        inicializarTabla() {
            const selector = '#table-evaluacion-requisitos-legales';
            if (!window.jQuery?.fn?.DataTable || !document.querySelector(selector)) return;

            const escaparAtributo = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[caracter]);

            this.tabla = $(selector).DataTable({
                processing: true,
                serverSide: false,
                autoWidth: false,
                stateSave: true,
                order: [[1, 'desc']],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json',
                    emptyTable: 'No se encontró información para mostrar'
                },
                ajax: {
                    url: '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/datatable',
                    type: 'GET',
                    dataSrc: json => Array.isArray(json.data) ? json.data : []
                },
                columns: [
                    { data: 'numero', width: '48px', className: 'text-center fw-bold' },
                    {
                        data: 'fecha_larga',
                        className: 'text-center',
                        render: (fecha, tipo, fila) => tipo === 'display' || tipo === 'filter'
                            ? fecha
                            : (fila.fecha || fecha)
                    },
                    {
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        render: (data, tipo, fila) => {
                            if (!fila.archivo || !fila.url_pdf) {
                                return '<i class="ti ti-file-off fs-6 text-muted" aria-label="Documento no disponible"></i>';
                            }

                            const url = escaparAtributo(fila.url_pdf);
                            const nombre = escaparAtributo(fila.archivo);
                            return `<a href="${url}" download="${nombre}" data-download-loader aria-label="Descargar evaluación"><i class="ti ti-download fs-6 text-primary"></i></a>`;
                        }
                    },
                    {
                        data: 'id',
                        width: '48px',
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        render: id => `<a class="pointer" data-evaluacion-accion="eliminar" data-id="${Number(id)}" aria-label="Eliminar evaluación"><i class="ti ti-trash fs-6 text-danger"></i></a>`
                    }
                ]
            });

            $(selector).on('click.evaluacionRequisitosLegales', '[data-evaluacion-accion="eliminar"]', event => {
                event.preventDefault();
                const id = Number(event.currentTarget.dataset.id);
                window.evaluacionRequisitosLegales?.eliminar(id);
            });
        },

        async buscar() {

            if (!this.tabla) return;

            await new Promise(resolve => this.tabla.ajax.reload(resolve, false));
        },

        //----------------------------------------------------------

        openNuevo(){
        this.limpiar();
        this.modalNuevo.show();
        },

        limpiar(){
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            fecha: '';
            documento: null;

        },

        validar(){
        Object.keys(this.errors).forEach(k => this.errors[k] = false);
        let valid = true;

        if (!this.fecha) {

            this.errors.fecha = true;
            valid = false;
        }
        if (!this.documento) {
            this.errors.documento = true;
            valid = false;
        }

        return valid;

        },

    async guardar()
    {

        if (!this.validar()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        try {

            const formData = new FormData();

            formData.append(
                'fecha',
                this.fecha
            );

            formData.append(
                    'documento',
                    this.documento
            );

            const url = '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/create';

            const res =
                await this.createAction({
                    url,
                    data: formData
                });

            if (res && res.success) {

            await this.buscar();
            this.modalNuevo.hide();
            this.limpiar();

            }

        } catch(error){

            this.notify(
                'error',
                'Error al guardar protocolo'
            );
        }
    },

    async eliminar(id)
    {
        const res =
            await this.deleteAction({
                url:'/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/delete',
                id,
                name: 'Informe'
            });

        if(!res?.success){
            return;
        }

        await this.buscar();
    },

    }));
});
