document.addEventListener('alpine:init', () => {

    Alpine.data('recepcionDescargaProducto', () => ({

        modalBuscar: null,

        years: [],

        filtros: {
            year: '',
            mes: ''
        },
        errors: {
        year: false
        },

        modalDetalle: null,
       detalle: {
            tanques: {
                items: [],
                merma: '0.00'
            },
            sellos: {
                sellos: [],
                nice: []
            }
        },

        pdfUrl: '/sasisopa/control-actividades-procesos/recepcion-descarga-producto/pdf?year=',

        init() {
            const currentYear = new Date().getFullYear();
            window.recepcionDescargaProducto = this;
            this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));
            this.modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalle'));
            this.pdfUrl = '/sasisopa/control-actividades-procesos/recepcion-descarga-producto/pdf?year=' + currentYear;
            
            for (let i = 2020; i <= currentYear; i++) {
                this.years.push(i);
            }

        },

        openBuscarModal() {
            this.modalBuscar.show();
        },

        validate() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.filtros.year) {
            this.errors.year = true;
            valid = false;
            }

            return valid;
        },

        limpiar(){
             Object.keys(this.errors).forEach(k => this.errors[k] = false);
        },

        buscar() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

               const url =
                    '/sasisopa/control-actividades-procesos/recepcion-descarga-producto/datatable'
                    + '?year=' + this.filtros.year
                    + '&mes=' + this.filtros.mes;

                table1
                    .ajax
                    .url(url)
                    .load();

                bootstrap.Modal
                .getInstance(document.getElementById('ModalBuscar'))
                .hide();

                this.pdfUrl = '/sasisopa/control-actividades-procesos/recepcion-descarga-producto/pdf?year=' + this.filtros.year + '&mes=' + this.filtros.mes;
            
        },

        openModal(data) {

            this.detalle = data;
            console.log(this.detalle);
            this.modalDetalle.show();
            
        }

    }));

});