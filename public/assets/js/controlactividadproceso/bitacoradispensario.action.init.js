document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraDispensario', () => ({

        excelUrl: '',
        modalNuevo: null,
        modalBuscar: null,
        modalDetalle: null,
        years: [],
        periodoTexto: '',
        dispensarios: [],
        productos: [],

        nuevo: {
            motivo: '',
            fecha: '',
            hora_inicio: '',
            hora_termino: '',
            id_dispensario: '',
            lado: '',
            producto: '',
            detalle: ''
        },

        errorsNuevo: {
            motivo: false,
            fecha: null,
            hora_inicio: null,
            hora_termino: null,
            id_dispensario: null,
            lado: null,
            producto: null,
            detalle: null,
        },

        filtro: {
            year: '',
            mes: ''
        },

        errorsBuscar: {
        year: false
        },    
        
        detalleRegistro: {},

    init(){
        const currentYear = new Date().getFullYear();
       
        window.bitacoraDispensario = this;
        this.modalNuevo = new bootstrap.Modal(document.getElementById('ModalNuevo'));
        this.modalBuscar = new bootstrap.Modal(document.getElementById('ModalBuscar'));
        this.modalDetalle = new bootstrap.Modal(document.getElementById('ModalDetalle'));

        this.excelUrl = '/sasisopa/control-actividades-procesos/bitacora-dispensario/excel?year=' + currentYear;
        this.periodoTexto = String(currentYear);

         for(let i = 2020; i <= currentYear; i++){
        this.years.push(i);
        }

        this.$nextTick(() => {
            this.filtro.year = currentYear;
        });

        this.loadCatalogos();
    },

     validateNuevo(){

            let valid = true;

            Object.keys(this.errorsNuevo)
                .forEach(k => this.errorsNuevo[k] = false);

            if (!this.nuevo.motivo) {
                this.errorsNuevo.motivo = true;
                valid = false;
            }

            if(!this.nuevo.fecha){
                this.errorsNuevo.fecha = true;
                valid = false;
            }

            if(!this.nuevo.hora_inicio){
                this.errorsNuevo.hora_inicio = true;
                valid = false;
            }

            if (!this.nuevo.hora_termino) {
                this.errorsNuevo.hora_termino = true;
                valid = false;
            }

            if (this.nuevo.motivo != 2 && !this.nuevo.id_dispensario) {
                this.errorsNuevo.id_dispensario = true;
                valid = false;
            }

            if (this.nuevo.motivo != 2 && !this.nuevo.lado) {
                this.errorsNuevo.lado = true;
                valid = false;
            }

            if ((this.productos.length > 0 || this.nuevo.motivo == 2) && !this.nuevo.producto) {
                this.errorsNuevo.producto = true;
                valid = false;
            }

            if (!String(this.nuevo.detalle || '').trim()) {
                this.errorsNuevo.detalle = true;
                valid = false;
            }


            return valid;

        },

    limpiarNuevo(){

        this.nuevo = {
            motivo: '',
            fecha: '',
            hora_inicio: '',
            hora_termino: '',
            id_dispensario: '',
            lado: '',
            producto: '',
            detalle: ''
        };
        Object.keys(this.errorsNuevo).forEach(k => this.errorsNuevo[k] = false);
    },

    async loadCatalogos(){

        const response = await fetch(
            '/sasisopa/control-actividades-procesos/bitacora-dispensario/catalogos'
        );

        const data = await response.json();

        this.dispensarios = data.dispensarios;
        this.productos = data.productos;
    },

    openNuevoModal(){

    this.limpiarNuevo();
    this.modalNuevo.show();
    },

    async guardar(){

        if(!this.validateNuevo()){
            this.notify('error','Completa todos los campos');
            return;
        }

    try{

        let url = '/sasisopa/control-actividades-procesos/bitacora-dispensario/create';

        const res = await this.createAction({
            url,
            data: {
            motivo: this.nuevo.motivo,
            fecha: this.nuevo.fecha,
            hora_inicio: this.nuevo.hora_inicio,
            hora_termino: this.nuevo.hora_termino,
            id_dispensario: this.nuevo.id_dispensario,
            lado: this.nuevo.lado,
            producto: this.nuevo.producto,
            detalle: this.nuevo.detalle
            },
            table: '#table-bitacora-dispensario'
        });

                if (res && res.success) {
                   this.modalNuevo.hide();
                   this.limpiarNuevo();
                }

    
    }catch(error){
       this.notify('error','Error al guardar');
    }
    },

    //-----------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------

    detalle(row) {
        this.detalleRegistro = row;
        this.modalDetalle.show();
    },

    //-----------------------------------------------------------------------------------------

    limpiarFiltros() {

        const year = new Date().getFullYear();
        this.filtro = {
            year,
            mes: ''
        };

        table1.ajax.url(
            '/sasisopa/control-actividades-procesos/bitacora-dispensario/datatable?year=' + year
        ).load();

        this.excelUrl =
            '/sasisopa/control-actividades-procesos/bitacora-dispensario/excel?year=' + year;
        this.periodoTexto = String(year);

        this.modalBuscar.hide();
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

        const url = '/sasisopa/control-actividades-procesos/bitacora-dispensario/datatable'
            + '?year=' + this.filtro.year
            + '&mes=' + this.filtro.mes;

            table1
            .ajax
            .url(url)
            .load();

            bootstrap.Modal
            .getInstance(document.getElementById('ModalBuscar'))
            .hide();

            this.excelUrl = '/sasisopa/control-actividades-procesos/bitacora-dispensario/excel?year=' + this.filtro.year + '&mes=' + this.filtro.mes;
            this.actualizarPeriodo(this.filtro.year, this.filtro.mes);

    },

    actualizarPeriodo(year, mes) {
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        this.periodoTexto = mes ? `${meses[Number(mes) - 1]} ${year}` : String(year);
    },

    async descargarExcel() {
        if (window.loader) window.loader.show();

        try {
            const response = await fetch(this.excelUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('No fue posible descargar el archivo');

            const blob = await response.blob();
            const contentType = response.headers.get('Content-Type') || blob.type;
            if (!contentType.toLowerCase().includes('text/csv')) {
                throw new Error('El servidor no generó el archivo CSV');
            }

            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = 'Bitacora-dispensarios.csv';
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

    async eliminar(id, name){

            const res = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/bitacora-dispensario/delete',
                id,
                name,
                table: '#table-bitacora-dispensario'
            });

         }

    }));
});
