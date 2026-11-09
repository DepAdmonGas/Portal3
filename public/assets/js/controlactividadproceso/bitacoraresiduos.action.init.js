document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraResiduos', () => ({

        pdfUrl: '',
        periodoTexto: '',
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

            this.filtro.year = currentYear;
            this.actualizarPeriodo(currentYear, '');
            this.pdfUrl = '/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos/pdf?year=' + currentYear + '&mes=';

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
            this.actualizarPeriodo(this.filtro.year, this.filtro.mes);

    },

    actualizarPeriodo(year, mes) {
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        const nombreMes = mes ? meses[Number(mes) - 1] : '';
        this.periodoTexto = nombreMes ? `${nombreMes} ${year}` : String(year);
    },

    async descargarPdf() {
        if (window.loader) window.loader.show();

        try {
            const response = await fetch(this.pdfUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('No fue posible descargar el archivo');

            const blob = await response.blob();
            if (!blob.type.toLowerCase().includes('pdf')) {
                throw new Error('El servidor no generó el PDF solicitado');
            }

            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = 'Bitacora-Residuos-Peligrosos.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        } catch (error) {
            this.notify('error', error.message || 'Error al descargar el archivo');
        } finally {
            if (window.loader) window.loader.hide();
        }
    },

    }));
});
