document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraResiduos', () => ({

        pdfUrl: '',
        modalDetalle: null,
        detalleRegistro: {},

        modalBuscar: null,
        years: [],

        filtro: {
            year: '',
            mes: ''
        },

        errorsBuscar: {
        year: false
        },    
       
        init() {
            const currentYear = new Date().getFullYear();

            window.bitacoraResiduos = this;
            this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));
            this.modalDetalle = new bootstrap.Modal(
                document.getElementById('ModalDetalle')
            );

            this.pdfUrl = '/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos/pdf';

              for(let i = 2020; i <= currentYear; i++){
        this.years.push(i);
        }
           
        },

       detalle(row){

            this.detalleRegistro = row;

            this.modalDetalle.show();
        },


    limpiarBuscar(){
        Object.keys(this.errorsBuscar).forEach(k => this.errorsBuscar[k] = false);
    },

    openBuscarModal(){
        this.modalBuscar.show();
    },

    validateBuscar() {
        Object.keys(this.errorsBuscar).forEach(k => this.errorsBuscar[k] = false);
        let valid = true;

        if (!this.filtro.year) {
        this.errorsBuscar.year = true;
        valid = false;
        }

        return valid;
    },

    async buscar(){

        if (!this.validateBuscar()) {
            this.notify('error', 'Completa todos los campos obligatorios');
            return;
        }

        const url = '/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos/datatable'
            + '?year=' + this.filtro.year
            + '&mes=' + this.filtro.mes;

            table1
            .ajax
            .url(url)
            .load();

            bootstrap.Modal
            .getInstance(document.getElementById('ModalBuscar'))
            .hide();

            this.pdfUrl = '/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos/pdf?year=' + this.filtro.year + '&mes=' + this.filtro.mes;

    },

    }));
});