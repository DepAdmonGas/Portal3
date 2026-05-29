document.addEventListener('alpine:init', () => {
    Alpine.data('detectorHumo', () => ({

        modalNuevo:  null,
        no_detector: '',
        ubicacion: '',
        errors: {
            no_detector: null,
            ubicacion: null,
        },

       
        init() {

            window.detectorHumo = this;
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
           
        },

        validate(){

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if(!this.no_detector){
                this.errors.no_detector = true;
                valid = false;
            }

            if(!this.ubicacion){
                this.errors.ubicacion = true;
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
            this.no_detector= '';
            this.ubicacion= '';

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
             
                 let url = '/sasisopa/control-actividades-procesos/detector-humo/create';

                 const res = await this.createAction({
                    url,
                    data: {
                     detector: this.no_detector,
                     ubicacion: this.ubicacion
                    },
                    table: '#table-detector-humo'
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
                url: '/sasisopa/control-actividades-procesos/detector-humo/delete',
                id,
                name,
                table: '#table-detector-humo'
            });

        },

    }));
});