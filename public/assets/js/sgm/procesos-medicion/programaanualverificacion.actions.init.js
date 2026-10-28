document.addEventListener('alpine:init', () => {

    Alpine.data('programacionAnual', () => ({

        year: new Date().getFullYear(),
        formato: 15,
        lista: [],
        loading:false,

        modalNuevo:null,
        equipos:[],

        formNuevo:{
            equipo_id:'',
            fecha:''
        },

        errors: {
          equipo_id: false,
          fecha: false,
        },

        pdf: '',

        init(){

            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
            const yearContainer = document.getElementById('container');
            const initialYear = Number(yearContainer?.dataset.year);
            if (Number.isInteger(initialYear) && initialYear >= 2020 && initialYear <= new Date().getFullYear()) {
                this.year = initialYear;
            }
            this.actualizarYearBreadcrumb();
            document.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : event.target?.parentElement;
                const opcion = target?.closest('[data-sgm-programa-year]');
                if (!opcion) return;
                event.preventDefault();
                this.cambiarYear(opcion.dataset.sgmProgramaYear);
            });
            this.obtenerTabla(this.year);

            this.pdf = '/sgm/procesos-medicion/programa-anual-verificacion-equipos/pdf/' + this.year + '/' + this.formato;

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

        cambiarYear(year) {
            const seleccionado = Number(year);
            if (!Number.isInteger(seleccionado) || seleccionado < 2020 || seleccionado > new Date().getFullYear()) return;
            this.year = seleccionado;
            this.actualizarYearBreadcrumb();
            this.pdf = '/sgm/procesos-medicion/programa-anual-verificacion-equipos/pdf/' + this.year + '/' + this.formato;
            this.obtenerTabla(this.year);
        },

        actualizarYearBreadcrumb() {
            const etiqueta = document.getElementById('programa-medicion-year-label');
            if (etiqueta) etiqueta.textContent = this.year;
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


        async eliminar(id, name){

          const res=await this.deleteAction({

            url:'/sgm/procesos-medicion/programa-anual-verificacion-equipos/delete',

            id:id,

            name:name

        });

        if(res.success){

            await this.obtenerTabla(this.year);

        }

        }
       

    }));

});
