document.addEventListener('alpine:init', () => {
    const textoSeguro = valor => $('<div>').text(String(valor ?? '')).html();
    const rutaArchivo = archivo => String(archivo ?? '')
        .split('/')
        .map(segmento => encodeURIComponent(segmento))
        .join('/');

    Alpine.data('revisionResultados', () => ({

        year: new Date().getFullYear(),

        get pdfUrl() {
        return `/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/${this.year}`;
        },

        implementacion: {
            meta: '',
            resultado: ''
        },

        ventas: {
            meta: '',
            detalle: []
        },

        capacitacion: {
            meta: '',
            semestre1: null,
            semestre2: null
        },

        satisfaccion: {
            meta: '',
            semestre1: null,
            semestre2: null
        },

        incidentes: {
            meta: '',
            semestre1: '',
            semestre2: null
        },

        revisionResultados: [],
        modalRevisionResultado: null,

        modoRevision: 'create',

        revision: {
            id: null,
            fecha: '',
            archivo: null,
            archivo_actual: ''
        },

        errors:{
            fecha: false
        },


        loading: false,

        async init() {
            const selectedYear = Number(document.getElementById('container')?.dataset.year);
            if (selectedYear >= 2019) this.year = selectedYear;

            window.revisionResultados = this;
            this.inicializarTablaRevisionResultados();
            this.pdfUrl = '/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/' + this.year;
            await this.buscar();
            this.cargarRevisionResultados();

             this.modalRevisionResultado =
        new bootstrap.Modal(
            document.getElementById(
                'modalRevisionResultado'
            )
        );


        },

        inicializarTablaRevisionResultados() {
            const selector = '#table-revision-resultados';
            if (!window.jQuery?.fn?.DataTable || !document.querySelector(selector)) return;

            window.tableRevisionResultados = $(selector).DataTable({
                processing: true,
                serverSide: false,
                autoWidth: false,
                stateSave: true,
                order: [],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json',
                    emptyTable: 'No se encontró información para mostrar'
                },
                data: [],
                columns: [
                    { data: 'id', className: 'text-center fw-bolder align-middle' },
                    {
                        data: 'fecha_larga',
                        className: 'text-center align-middle',
                        render: (fecha, tipo, fila) => tipo === 'sort' || tipo === 'type'
                            ? fila.fecha
                            : textoSeguro(fecha)
                    },
                    { data: 'usuario', defaultContent: 'S/I', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                    {
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        render: (dato, tipo, fila) => {
                            const id = Number(fila.id);
                            const archivo = String(fila.archivo ?? '');
                            const nombreArchivo = archivo.split('/').pop() || 'documento';
                            const descarga = fila.tiene_archivo && archivo
                                ? `<a class="dropdown-item pointer d-flex align-items-center gap-3" href="${textoSeguro(rutaArchivo(archivo))}" download="${textoSeguro(nombreArchivo)}" data-download-loader><i class="fs-4 ti ti-download"></i>Descargar</a>`
                                : '<span class="dropdown-item d-flex align-items-center gap-3 text-muted"><i class="fs-4 ti ti-download"></i>Descargar</span>';

                            return `
                                <div class="dropdown dropstart">
                                    <a class="pointer" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones del registro">
                                        <i class="ti ti-dots-vertical fs-6"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-revision-accion="editar" data-id="${id}"><i class="fs-4 ti ti-edit"></i>Editar</a></li>
                                        <li>${descarga}</li>
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-revision-accion="eliminar" data-id="${id}"><i class="fs-4 ti ti-trash"></i>Eliminar</a></li>
                                    </ul>
                                </div>`;
                        }
                    }
                ]
            });

            $(selector)
                .off('click.revisionResultados', '[data-revision-accion]')
                .on('click.revisionResultados', '[data-revision-accion]', event => {
                    event.preventDefault();
                    const componente = window.revisionResultados;
                    const id = Number(event.currentTarget.dataset.id);
                    const accion = event.currentTarget.dataset.revisionAccion;
                    const registro = componente?.revisionResultados.find(item => Number(item.id) === id);
                    if (!componente || !registro) return;

                    if (accion === 'editar') componente.editarRevision(registro);
                    if (accion === 'eliminar') componente.eliminar(id);
                });
        },

        async buscar() {

             this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/monitoreo-verificacion-evaluacion/buscar-indicadores',
                    {
                        params: {
                            year: this.year
                        }
                    }
                );

     
                this.implementacion = data.implementacion;
                this.ventas = data.ventas;
                this.capacitacion = data.capacitacion;
                this.satisfaccion = data.satisfaccion;
                this.incidentes = data.incidentes;

                this.pdfUrl = '/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/' + this.year;

            } catch (error) {

                this.loading = false;

            } finally {

                this.loading = false;

            }

        },

        descargarPdf() {

            window.open(
                `/descargar-revision-resultados-detalle/${this.year}`,
                '_blank'
            );

        },

        async cargarRevisionResultados() {

        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/revision-resultados/datatable'
            );

            if (data.success) {

                this.revisionResultados =
                    data.data;
                window.tableRevisionResultados?.clear().rows.add(this.revisionResultados).draw(false);
            }

        } finally {

            this.loading = false;
        }
        },

        limpiarRevisionResultado(){

        this.revision = {
            id: null,
            fecha: '',
            archivo: null,
            archivo_actual: ''
        };

        this.limpiarInputArchivo();

        },

        limpiarInputArchivo() {

            const input = document.getElementById('archivo');

            if (input) {
                input.value = '';
            }

        },

    openModalRevisionResultado(){

        this.modoRevision = 'create';

        this.limpiarRevisionResultado();

        this.revision.fecha =
            new Date()
                .toISOString()
                .split('T')[0];

        this.modalRevisionResultado.show();
    },

    editarRevision(item){

        this.modoRevision = 'edit';
        this.revision.id = item.id;
        this.revision.fecha = item.fecha;
        this.revision.archivo_actual = item.archivo;
        this.revision.archivo = null;

        this.limpiarInputArchivo()
        this.modalRevisionResultado.show();
    },

    validarRevision()
    {
        let valido = true;
        this.errors.fecha = false;

        if (!this.revision.fecha) {
            this.errors.fecha = true;
            valido = false;
        }

        return valido;
    },

    async guardarRevisionResultado(){

        if (!this.validarRevision()) {
            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        const payload = new FormData();

        payload.append(
            'fecha',
            this.revision.fecha
        );

        if(this.revision.archivo){

            payload.append(
                'archivo',
                this.revision.archivo
            );
        }

        let url =
            '/sasisopa/revision-resultados/create';

        if(this.modoRevision === 'edit'){

            payload.append(
                'id',
                this.revision.id
            );

            url =
            '/sasisopa/revision-resultados/update';
        }

        const res =
            await this.createAction({

                url: url,

                data: payload
            });

        if(res.success){

            this.modalRevisionResultado.hide();
            this.cargarRevisionResultados();
        }
    },

    async eliminar(id) {

    const res = await this.deleteAction({

        url: '/sasisopa/revision-resultados/delete',
        id: id,
        name: id
    });

    if (res?.success) {

        this.cargarRevisionResultados();
    }
    }

    }));
});
