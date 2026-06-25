document.addEventListener('alpine:init', () => {
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