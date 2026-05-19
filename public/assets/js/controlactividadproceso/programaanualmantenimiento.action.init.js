document.addEventListener('alpine:init', () => {
    Alpine.data('programaAnualMantenimiento', () => ({

        idPrograma: '',
        modalNuevo: null,
        modalEditar: null,
        equipos: [],
        year: '',

        nuevo: {
            id_mantenimiento: '',
            periodicidad: '',
            ultimafecha: ''
        },

        errors: {
            id_mantenimiento: false,
            periodicidad: false,
            ultimafecha: false
        },

        editarData: {
            id: '',
            detalle: '',
            enero: '',
            febrero: '',
            marzo: '',
            abril: '',
            mayo: '',
            junio: '',
            julio: '',
            agosto: '',
            septiembre: '',
            octubre: '',
            noviembre: '',
            diciembre: ''
        },

        init(){
            window.programaMantenimiento = this;
            this.idPrograma = document.getElementById('container').dataset.idprograma;      
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));
            this.modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
        },

        validate(){

             Object.keys(this.errors).forEach(k => {this.errors[k] = false;});
             let valid = true;

            if (!this.nuevo.id_mantenimiento) {
            this.errors.id_mantenimiento = true;
            valid = false;
            }

            if (!this.nuevo.ultimafecha) {
            this.errors.ultimafecha = true;
            valid = false;
            }

             return valid;
             
        },

        limpiarNuevo(){

            Object.keys(this.errors).forEach(k => {this.errors[k] = false;});

             this.nuevo = {
                id_mantenimiento: '',
                periodicidad: '',
                ultimafecha: ''
            };
            this.equipos = [];

        },

        async openModalNuevo(){
            this.limpiarNuevo();
            await this.cargarEquipos();
            this.modalNuevo.show();
        },

        async cargarEquipos(){

            try{

                          
                const res = await axios.get(
                    '/sasisopa/control-actividades-procesos/equipos-programa-anual-mantenimiento/' + this.idPrograma
                );

                this.equipos = res.data.data || [];

            }catch(e){
                this.notify('error','Error al cargar equipos');
            }
        },

        selectEquipo(){

            const equipo =
            this.equipos.find(
                x => x.id ==
                this.nuevo.id_mantenimiento
            );

            if(!equipo){
                this.nuevo.periodicidad = '';
                return;
            }

            this.nuevo.periodicidad =  equipo.periodicidad;
        },

        async guardarNuevo(){


            if (!this.validate()) {    
                this.notify('error','Completa todos los campos obligatorios');
                return;
            }

            try {

                const res = await this.createAction({

                    url: '/sasisopa/control-actividades-procesos/create-programa-anual-mantenimiento',

                    data: {
                        id_programa: this.idPrograma,
                        id_mantenimiento: this.nuevo.id_mantenimiento,
                        periodicidad: this.nuevo.periodicidad,
                        ultimafecha: this.nuevo.ultimafecha
                    },

                    table: '#table-programa-anual'
                });

                if (res && res.success) {

                    this.limpiarNuevo();
                    this.modalNuevo.hide();
                }

            } catch (e) {

                this.notify(
                    'error',
                    'Error al guardar'
                );
            }
        },

        async eliminar(id, name){

            await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/delete-programa-anual-mantenimiento',
                id,
                name,
                table: '#table-programa-anual'
            });

        },

        limpiarEditar(){

            this.editarData = {
                id: '',
                detalle: '',
                enero: '',
                febrero: '',
                marzo: '',
                abril: '',
                mayo: '',
                junio: '',
                julio: '',
                agosto: '',
                septiembre: '',
                octubre: '',
                noviembre: '',
                diciembre: ''
            };
        },

        // Editar

        async openModalEditar(id){

        try {

            this.limpiarEditar();

            const res = await axios.get(
                '/sasisopa/control-actividades-procesos/get-programa-anual-mantenimiento/' + id
            );

            if (!res.data.success) {

                this.notify(
                    'error',
                    res.data.message || 'Registro no encontrado'
                );

                return;
            }

            this.editarData = res.data.data;
            console.log(res.data.data)

            this.modalEditar.show();

        } catch (e) {

            this.notify(
                'error',
                'Error al cargar la información'
            );
        }
    },

    async editar(){

        try {

            const payload = {
                id: this.editarData.id,
                meses: {}
            };

            Object.entries(
                this.editarData.meses
            ).forEach(([mes, config]) => {

                payload.meses[mes] =
                    config.value || null;
            });

            const res = await this.createAction({
                url: '/sasisopa/control-actividades-procesos/update-programa-anual-mantenimiento',
                data: payload,
                table: '#table-programa-anual'
            });

            if (res.success) {
                this.modalEditar.hide();
                this.limpiarEditar();
            }

        } catch (e) {

            this.notify(
                'error',
                'Error al actualizar'
            );
        }
    },
      
    }));
});
