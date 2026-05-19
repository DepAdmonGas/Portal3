document.addEventListener('alpine:init', () => {
    Alpine.data('equipoCritico', () => ({

        modalEquipo: null,

        equipo: {
            nombre: '',
            marca_modelo: '',
            funciones: '',
            fecha_instalacion: '',
            tiempo_vida: '',
            manual: null
        },

        errors: {
            nombre: false,
            marca_modelo: false,
            funciones: false,
            fecha_instalacion: false,
            tiempo_vida: false,
            manual: false
        },

        init(){
            window.equipoCritico = this;
             this.modalEquipo = new bootstrap.Modal(document.getElementById('modalEquipo'));
        },

         validate(){

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if(!this.equipo.nombre){
                this.errors.nombre = true;
                valid = false;
            }

            if(!this.equipo.marca_modelo){
                this.errors.marca_modelo = true;
                valid = false;
            }

            if(!this.equipo.funciones){
                this.errors.funciones = true;
                valid = false;
            }

            if(!this.equipo.fecha_instalacion){
                this.errors.fecha_instalacion = true;
                valid = false;
            }

            if(!this.equipo.tiempo_vida){
                this.errors.tiempo_vida = true;
                valid = false;
            }

            if(!this.equipo.manual){
                this.errors.manual = true;
                valid = false;
            }


            return valid;
        },

        limpiar(){

            this.equipo = {
                nombre: '',
                marca_modelo: '',
                funciones: '',
                fecha_instalacion: '',
                tiempo_vida: '',
                manual: null
            };

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);
        },

        closeModal(){
            if (this.modalEquipo) {
                this.modalEquipo.hide();
            }
        },

         openModal(){
            this.limpiar();
            this.modalEquipo.show();
        },

        async guardar(){

             if(!this.validate()){
                this.notify(
                    'error',
                    'Completa todos los campos'
                );
                return;
            }

            const formData = new FormData();
            const file = this.$refs?.fileManual?.files?.[0] || null;

            formData.append('nombre_equipo',this.equipo.nombre);
            formData.append('marca_modelo',this.equipo.marca_modelo);
            formData.append('funciones',this.equipo.funciones);
            formData.append('fecha_instalacion',this.equipo.fecha_instalacion);
            formData.append('tiempo_vida',this.equipo.tiempo_vida);
            formData.append('manual',file);

            let url = `/sasisopa/integridad-mecanica-aseguramiento/create-equipo-critico`;

            const res = await this.createAction({
                url,
                data: formData,
                table: '#table-equipo-critico'
            });

            if (res && res.success) {
                
                this.modalEquipo.hide();
                this.limpiar();
            }

        },

         async eliminar(id, name){

            await this.deleteAction({
                url: '/sasisopa/integridad-mecanica-aseguramiento/delete-equipo-critico',
                id,
                name,
                table: '#table-equipo-critico'
            });
        },

         async baja(id, name){

            await this.bajaAction({
                url: '/sasisopa/integridad-mecanica-aseguramiento/baja-equipo-critico',
                id,
                name,
                table: '#table-equipo-critico'
            });
        }


        

    }));
});