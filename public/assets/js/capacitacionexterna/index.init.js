document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacionExterna', () => ({

            mode: 'create',
            id: null,
            curso: '',
            fecha_programada: '',
            duracion: '',
            duraciondetalle: '',
            instructor: '',
            fecha_real: '',

            errors: {
            curso: false,
            fecha_programada: false,
            duracion: false,
            duraciondetalle: false,
            instructor: false,
            },

            modal: null,

            modalPersonal: null,
            personal: [],
            usuarios: [],
            selectedEmpleado: '',
            capacitacionId: null,

        init(){
            window.capacitacionExterna = this;
            this.modal = new bootstrap.Modal(document.getElementById('modal'));
            this.modalPersonal = new bootstrap.Modal(document.getElementById('modal-personal'));
        },

         validate() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.curso) {
            this.errors.curso = true;
            valid = false;
            }

            if (!this.fecha_programada) {
            this.errors.fecha_programada = true;
            valid = false;
            }            

            return valid;
        },

        closeModal() {
            this.modal.hide();
        },

        resetModal() {
            
                this.id = null;
                this.curso = '';
                this.fecha_programada = '';
                this.duracion = '';
                this.duraciondetalle = '';
                this.instructor = '';
                this.fecha_real = '';

                Object.keys(this.errors).forEach(k => this.errors[k] = false);
        },

        openModalNuevo() {
            this.mode = 'create';
            this.id = null;

            this.resetModal();
            this.modal.show();
        },

        async guardar(){

             if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

            let url = '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-capacitacion-externa';

            if (this.mode === 'edit') {
                url = `/sasisopa/competencia-personal-capacitacion-entrenamiento/update-capacitacion-externa/${this.id}`;
            }

            try {

                const res = await this.createAction({
                    url,
                    data: {
                     curso: this.curso,
                     fecha_programada: this.fecha_programada,
                     duracion: this.duracion,
                     duraciondetalle: this.duraciondetalle,
                     instructor: this.instructor,
                     fecha_real: this.fecha_real
                    },
                    table: '#table-capacitacion-externa'
                });

                if (res && res.success) {
                    this.closeModal();
                }

            } catch (e) {
                this.notify('error', 'Error al guardar');
            }

        },

        //-------------------------------------------------

        openModalEditar(data) {

            this.mode = 'edit';

            this.id = data.id;
            this.curso = data.curso;

            this.fecha_programada = data.fecha_programada 
                ? data.fecha_programada.split('T')[0] 
                : '';

            this.duracion = data.duracion;
            this.duraciondetalle = data.duraciondetalle;
            this.instructor = data.instructor;

            // 🔥 AQUÍ EL FIX
            this.fecha_real = data.fecha_real
                ? data.fecha_real.split('T')[0]
                : '';

            this.modal.show();
        },

        //---------------------------------------------------------

        closeModalPersonal(){
            this.modalPersonal.hide();
        },

        async openModalPersonal(id) {

            this.capacitacionId = id;
            this.selectedEmpleado = '';

            try {

                const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/get-personal/${id}`);
                const data = await res.json();

                this.personal = data.personal;
                this.usuarios = data.usuarios;

                this.modalPersonal.show();

            } catch (e) {
                this.notify('error', 'Error al cargar trabajadores');
            }
        },

        async addEmpleado(){

            if (!this.selectedEmpleado) {
                this.notify('error', 'Selecciona un trabajador');
                return;
            }

            try {

               const url = '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-personal';

               const res = await this.createAction({
                    url,
                    data: {
                      id_capacitacion: this.capacitacionId,
                      id_empleado: this.selectedEmpleado
                    }
                });

                if (res.success) {
                    this.openModalPersonal(this.capacitacionId); 
                }

            } catch (e) {
                this.notify('error', 'Error al agregar');
            }
        },

        async removeEmpleado(id){

            try {

                const res = await fetch('/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-personal', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });

                const data = await res.json();

                if (data.success) {
                    this.openModalPersonal(this.capacitacionId);
                }

            } catch (e) {
                this.notify('error', 'Error al eliminar');
            }
        },

        //---------------------------------------------------------------

        async delete(id, name) {

            const res = await this.deleteAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-capacitacion-externa',
                id,
                name,
                table: '#table-capacitacion-externa'
            });

        }

        //---------------------------------------------------

        

        

    }));
});