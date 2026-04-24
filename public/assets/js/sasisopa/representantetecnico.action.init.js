document.addEventListener('alpine:init', () => {
    Alpine.data('representanteTecnicoForm', () => ({

        loading: false,

       form: {
            nombre: '',
            fecha: '',
            pdf: null
        },

        errors: {
            nombre: false,
            fecha: false,
            pdf: false
        },
                

        validate() {

            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.form.nombre) {
                this.errors.nombre = true;
                valid = false;
            }

            if (!this.form.fecha) {
                this.errors.fecha = true;
                valid = false;
            }

            if (!this.form.pdf) {
                this.errors.pdf = true;
                valid = false;
            }

            return valid;
        },

        resetForm() {
            this.form = {
                nombre: '',
                fecha: '',
                pdf: null
            };

            this.errors = {
                nombre: false,
                fecha: false,
                pdf: false
            };

            // 🔥 LIMPIAR INPUT REAL
            if (this.$refs.pdfInput) {
                this.$refs.pdfInput.value = null;
            }
        },

        resetModal() {

            const modalEl = document.getElementById('openNuevoModal');

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.resetForm();
                document.body.focus();
            }, { once: true });

            // quitar foco
            if (document.activeElement) {
                document.activeElement.blur();
            }

            const modal = bootstrap.Modal.getInstance(modalEl);

            if (modal) {
                modal.hide();
            }
        },

        handleFile(e) {
            this.form.pdf = e.target.files[0];
        },
                        
        init() {
            window.representanteTecnico = this;
        },

        openNuevo(){
             
            this.resetForm();

            const modal = new bootstrap.Modal(document.getElementById('openNuevoModal'));
            modal.show();
        },

        async submit() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

             const formData = new FormData();
            formData.append('nombre', this.form.nombre);
            formData.append('fecha', this.form.fecha);
            formData.append('pdf', this.form.pdf);

            const res = await this.createAction({
                url: '/sasisopa/funciones-responsabilidades-autoridad/create-representante-tecnico',
                data: formData,
                table: '#table-lista-representante-tecnico'
            });


            if (res?.success) {
                bootstrap.Modal.getInstance(document.getElementById('openNuevoModal')).hide();
                this.resetForm();
            }

        },


        async eliminar(id) {

            const res = await this.deleteAction({
                url: '/sasisopa/funciones-responsabilidades-autoridad/delete-representante-tecnico',
                id: id,
                name: id,
                table: '#table-lista-representante-tecnico'
            });

        }

    
    }));

});