document.addEventListener('alpine:init', () => {

    Alpine.data('programacionAnual', () => ({

        year: new Date().getFullYear(),
        formato: 14,
        lista: [],
        loading:false,

        modalNuevo:null,
        modalBuscar:null,
        equipos:[],

        formNuevo:{
            equipo_id:'',
            fecha:''
        },

        formBuscar:{
            fecha_year:''
        },

        errors: {
          equipo_id: false,
          fecha: false,
          fecha_year: false,
        },

        pdf: '',

        init(){

            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
            this.modalBuscar = new bootstrap.Modal(document.getElementById('modalBuscar'));
            this.obtenerTabla(this.year);

            this.pdf = '/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/pdf/' + this.year + '/' + this.formato;

        },

        async obtenerTabla(year){

            this.loading = true;

            try{

                const {data} = await axios.get('/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/table-programa-calibracion-patrones',{

                    params:{
                        year:year,
                        formato:this.formato
                    }

                });

                this.lista = data.data;

            }catch(error){

                this.notify(
                    'error',
                    'No se encontro información.'
                );

            }finally{

                this.loading=false;

            }

        },

        async openModalNuevo() {

            this.resetFormNuevo();

            this.modalNuevo.show();

            try {

                const { data } = await axios.get(
                    '/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/modal-programa-calibracion-patrones',
                    {
                        params: {
                            formato: this.formato
                        }
                    }
                );

                this.equipos = data.equipos;

            } catch (error) {

                this.notify(
                    'error',
                    'No fue posible obtener los equipos.'
                );

            }

        },

        async guardar(){

            if (!this.validaNuevo()) {
                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );
                return;
            }

            const res = await this.createAction({

                url:'/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/create-programa-calibracion-patrones',

                data:this.formNuevo

            });

            if (!res?.success) return;

            this.modalNuevo.hide();

            await this.obtenerTabla(this.year);

        },

        validaNuevo() {

            this.resetErrors();

            let valid = true;

            if (!this.formNuevo.equipo_id) {
                this.errors.equipo_id = true;
                valid = false;
            }

            if (!this.formNuevo.fecha) {
                this.errors.fecha = true;
                valid = false;
            }

            return valid;

        },

        openModalBuscar() {

            this.resetFormBuscar();

            this.modalBuscar.show();

        },

        async buscar(){

            if (!this.validaBuscar()) {
                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );
                return;
            }

            this.modalBuscar.hide();

            await this.obtenerTabla(
                this.formBuscar.fecha_year
            );

            this.pdf = '/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida/pdf/' + this.formBuscar.fecha_year + '/' + this.formato;

        },

        validaBuscar() {

            this.resetErrors();

            let valid = true;

            if (!this.formBuscar.fecha_year) {
                this.errors.fecha_year = true;
                valid = false;
            }

            return valid;

        },

        resetErrors() {

            Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

        resetFormNuevo() {

            this.formNuevo = {
                equipo_id: '',
                fecha: ''
            };

            this.resetErrors();

        },

        resetFormBuscar() {

            this.formBuscar = {
                fecha_year: this.year
            };

            this.resetErrors();

        },
       

    }));

});