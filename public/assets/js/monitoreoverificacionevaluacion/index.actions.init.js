document.addEventListener('alpine:init', () => {
    Alpine.data('monitoreoEvaluacion', () => ({

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


        loading: false,

        async init() {
            this.pdfUrl = '/sasisopa/monitoreo-verificacion-evaluacion/descargar-revision-resultados-detalle/' + this.year;
            await this.buscar();
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

        implementacionDetalle() {

            window.location.href =
                '/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa';

        },

        VentasDetalle(){
             window.location.href =
                '/sasisopa/monitoreo-verificacion-evaluacion/ventas-mes';
        },

        satisfaccionClientes(){
            window.location.href =
                '/sasisopa/objetivos-metas-indicadores/experiencia-cliente';
        },

        IncidentesAccidentes() {

            window.location.href =
                '/sasisopa/investigacion-incidentes-accidentes';

        },


    }));
});