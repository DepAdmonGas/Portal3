document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraDispensario', () => ({

        excelUrl: '',
        modalNuevo: null,
        modalBuscar: null,
        modalDetalle: null,
        years: [],
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
            fecha: null,
            hora_inicio: null,
            id_dispensario: null,
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

            if(!this.nuevo.fecha){
                this.errorsNuevo.fecha = true;
                valid = false;
            }

            if(!this.nuevo.hora_inicio){
                this.errorsNuevo.hora_inicio = true;
                valid = false;
            }

            if(!this.nuevo.id_dispensario){
                this.errorsNuevo.id_dispensario = true;
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

        this.filtro = {
            year: '',
            mes: ''
        };

        table1.ajax.url(
            '/sasisopa/control-actividades-procesos/bitacora-dispensario/datatable'
        ).load();

        this.excelUrl =
            '/sasisopa/control-actividades-procesos/bitacora-dispensario/excel';

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