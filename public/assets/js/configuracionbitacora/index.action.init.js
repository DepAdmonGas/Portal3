document.addEventListener('alpine:init', () => {
    Alpine.data('configuracionBitacora', () => ({

        modalNuevo: null,
        usuarios: [],
        usuarioSeleccionado: '',
        categorias: [],
        categoriasSeleccionadas: [],

        errorsNuevo: {
            usuarioSeleccionado: false,
            categoriasSeleccionadas: false
        },

        modalEliminar: null,
        titulo: '',
        text_usuario: '',
        id_autorizacion: '',
        nombre: '',
        categoria: '',
        comentario: '',

        errors: {
            comentario: false
        },


        init(){
            window.configuracionBitacora = this;
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo')); 
            this.modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));    
        },

        //---------- Nuevo
        limpiarNuevo(){
            this.usuarioSeleccionado = '';
            this.categorias = [];
            this.categoriasSeleccionadas = [];
        },

        validateNuevo(){

            Object.keys(this.errorsNuevo).forEach(k => {this.errorsNuevo[k] = false;});

            let valid = true;

            if (!this.usuarioSeleccionado) {
                this.errorsNuevo.usuarioSeleccionado = true;
                valid = false;
            }

            if (!Array.isArray(this.categoriasSeleccionadas) || this.categoriasSeleccionadas.length === 0) {
                this.errorsNuevo.categoriasSeleccionadas = true;
                valid = false;
            }

            return valid;
        },

        async modalNuevoOpen(){

            this.limpiarNuevo();
            const response = await fetch('/sasisopa/control-actividades-procesos/get-trabajador-autorizado');
            const json = await response.json();
            this.usuarios = json.data;
            this.modalNuevo.show();

        },

        changeUsuario() {

            const usuario = this.usuarios.find(u => u.id == this.usuarioSeleccionado);
            this.categorias = usuario?.faltantes ?? [];
            this.categoriasSeleccionadas = [];

        },

        async guardar(){

            if (!this.validateNuevo()) {
                this.notify('error','Selecciona usuario y al menos una categoría');
                return;
            }

            try {
                const res = await this.createAction({
                url: '/sasisopa/control-actividades-procesos/create-trabajador-autorizado',
                data: {
                    id_usuario: this.usuarioSeleccionado,
                    categorias: this.categoriasSeleccionadas
                    },
                    table: '#table-trabajador-autorizado'
                });

                if (res && res.success) {
                    this.limpiarNuevo();
                    this.modalNuevo.hide();
                }

            } catch (e) {

                this.notify('error','Error al guardar');
            }

        },


        //--------- Eliminar

        validateEliminar(){

             Object.keys(this.errors).forEach(k => {this.errors[k] = false;});
             let valid = true;

            if (!this.comentario) {
            this.errors.comentario = true;
            valid = false;
            }

             return valid;

        },

        limpiarEliminar(){

            this.categoria = '';

            Object.keys(this.errors).forEach(k => {this.errors[k] = false;});
        },

        modalEliminarOpen(id, name, categoria){

            this.limpiarEliminar();
            this.modalEliminar.show();
            
            this.titulo = 'Eliminar ' + categoria;
            this.text_usuario = name;

            this.id_autorizacion = id;
            this.nombre = name;
            this.categoria = categoria;



        },

        async eliminar() {

            if (!this.validateEliminar()) {    
                this.notify('error','Completa todos los campos obligatorios');
                return;
            }

       const result = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: `El registro: ${this.categoria} será eliminado`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

         const res = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/delete-trabajador-autorizado',
            data: {
                id: this.id_autorizacion,
                comentario: this.comentario
                },

                table: '#table-trabajador-autorizado'
            });

            if (res && res.success) {
                this.limpiarEliminar();
                this.modalEliminar.hide();
            }

    }       

    }));
});