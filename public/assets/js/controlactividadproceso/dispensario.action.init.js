document.addEventListener('alpine:init', () => {
    Alpine.data('dispensario', () => ({

        modalNuevo:  null,
        id_dispensario: null,

        no_dispensario: '',
        marca: '',
        modelo: '',
        serie: '',
        producto1: '',
        producto2: '',
        producto3: '',

        errors: {
            no_dispensario: null,
            marca: null,
            modelo: null,
            serie: null,
            producto1: null,
            producto2: null,
            producto3: null,
        },
       
        init() {

            window.dispensario = this;
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
           
        },

        validate(){

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if(!this.no_dispensario){
                this.errors.no_dispensario = true;
                valid = false;
            }

            if(!this.marca){
                this.errors.marca = true;
                valid = false;
            }

            if(!this.modelo){
                this.errors.modelo = true;
                valid = false;
            }

            if(!this.serie){
                this.errors.serie = true;
                valid = false;
            }

            return valid;

        },

        closeModal(){
            if (this.modalNuevo) {
                this.modalNuevo.hide();
            }
        },

        limpiar(){
            this.no_dispensario = '';
            this.marca = '';
            this.modelo = '';
            this.serie = '';
            this.producto1 = '';
            this.producto2 = '';
            this.producto3 = '';

            Object.keys(this.errors).forEach(k => this.errors[k] = false);

        },

        modalopen(){

            this.limpiar();
            this.modalNuevo.show();

        },

         async guardar(){

             if(!this.validate()){
                this.notify('error','Completa todos los campos');
                return;
            }

             try{
             
                 let url = '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/create';

                 const res = await this.createAction({
                    url,
                    data: {
                    no_dispensario: this.no_dispensario,
                    marca: this.marca,
                    modelo: this.modelo,
                    serie: this.serie,
                    producto1: this.producto1,
                    producto2: this.producto2,
                    producto3: this.producto3
                    },
                    table: '#table-dispensario'
                });

                if (res && res.success) {
                   this.modalNuevo.hide();
                   this.limpiar();
                }

            }catch(e){

                this.notify('error','Error al guardar');
            } 

        },

         async eliminar(id, name){

            const res = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/delete',
                id,
                name,
                table: '#table-dispensario'
            });

         }

    }));
});