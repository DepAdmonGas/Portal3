document.addEventListener('alpine:init', () => {
    Alpine.data('mejoresPracticasEstandares', () => ({

        modalOM: null,
        modalDC: null,

         dc: {
            codigo: '',
            area: ''
        },

        errorsDC: {
            codigo: false,
            area: false
        },

        om: {
            fecha: '',
            norma: '',
            nombre: '',
            link: ''
        },

        errorsOM: {
            fecha: false,
            norma: false,
            nombre: false,
            link: false
        },

        init(){

            window.mejoresPracticasEstandares = this;
            this.modalDC = new bootstrap.Modal(document.getElementById('modalDC'));
            this.modalOM = new bootstrap.Modal(document.getElementById('modalOM'));
            
        },

        //------- Diseño Construcccion

         validateDC(){

            let valid = true;

            Object.keys(this.errorsDC)
                .forEach(k => this.errorsDC[k] = false);

            if(!this.dc.codigo){

                this.errorsDC.codigo = true;
                valid = false;
            }

            if(!this.dc.area){

                this.errorsDC.area = true;
                valid = false;
            }

            return valid;
        },

         limpiarDC(){

            this.dc = {
                codigo: '',
                area: ''
            };

            Object.keys(this.errorsDC)
                .forEach(k => this.errorsDC[k] = false);
        },

         closeModalDC() {

            if (this.modalDC) {
                this.modalDC.hide();
            }
        },

         openModalDC(){

            this.limpiarDC();
            this.modalDC.show();
        },

        async guardarDC(){

            if(!this.validateDC()){
                this.notify('error','Completa todos los campos');
                return;
            }

            try{
             
                 let url = '/sasisopa/mejores-practicas-estandares/create-diseno-construccion';

                 const res = await this.createAction({
                    url,
                    data: {
                     codigo: this.dc.codigo,
                     area: this.dc.area
                    },
                    table: '#table-diseno-construccion'
                });

                if (res && res.success) {
                   this.modalDC.hide();
                   this.limpiarDC();
                }

            }catch(e){

                this.notify('error','Error al guardar');
            }
        },

        async eliminarDC(id, name){

             const res = await this.deleteAction({
                url: '/sasisopa/mejores-practicas-estandares/delete-diseno-construccion',
                id,
                name,
                table: '#table-diseno-construccion'
            });

        },

        //-----------------------------------------------------------

         validateOM(){

            let valid = true;

            Object.keys(this.errorsOM)
                .forEach(k => this.errorsOM[k] = false);

            if(!this.om.fecha){
                this.errorsOM.fecha = true;
                valid = false;
            }

            if(!this.om.norma){
                this.errorsOM.norma = true;
                valid = false;
            }

            if(!this.om.nombre){
                this.errorsOM.nombre = true;
                valid = false;
            }

            if(!this.om.link){
                this.errorsOM.link = true;
                valid = false;
            }

            return valid;
        },

         limpiarOM(){

            this.om = {
                fecha: '',
                norma: '',
                nombre: '',
                link: ''
            };

            Object.keys(this.errorsOM)
                .forEach(k => this.errorsOM[k] = false);
        },

         closeModalOM() {

            if (this.modalOM) {
                this.modalOM.hide();
            }
        },

        openModalOM(){

            this.limpiarOM();
            this.modalOM.show();
        },

         async guardarOM(){

            if(!this.validateOM()){
                this.notify('error','Completa todos los campos');
                return;
            }

            try{
             
                 let url = '/sasisopa/mejores-practicas-estandares/create-operacion-mantenimiento';

                 const res = await this.createAction({
                    url,
                    data: {
                     fecha: this.om.fecha,
                     norma: this.om.norma,
                     nombre: this.om.nombre,
                     link: this.om.link
                    },
                    table: '#table-operacion-mantenimiento'
                });

                if (res && res.success) {
                   this.modalOM.hide();
                   this.limpiarOM();
                }

            }catch(e){

                this.notify('error','Error al guardar');
            }
        },

        async eliminarOM(id, name){

             const res = await this.deleteAction({
                url: '/sasisopa/mejores-practicas-estandares/delete-operacion-mantenimiento',
                id,
                name,
                table: '#table-operacion-mantenimiento'
            });

        },

       
    
    }));
});
