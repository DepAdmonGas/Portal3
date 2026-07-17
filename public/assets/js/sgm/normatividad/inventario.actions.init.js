document.addEventListener('alpine:init', () => {

    Alpine.data('inventarioNormatividad', () => ({

        modal: null,

        form: {

            norma: '',

            fecha_publicacion: '',

            fecha_aplicacion: '',

            equipo: '',

            link: ''

        },

        errors: {
            norma: false,
            fecha_publicacion: false,
            equipo: false,
            link: false
        },

        init() {

            this.modal = new bootstrap.Modal(
                document.getElementById('modalNormatividad')
            );

        },

        nuevo() {

            this.form = {
                norma: '',
                fecha_publicacion: '',
                fecha_aplicacion: '',
                equipo: '',
                link: ''
            };

            this.modal.show();

        },

        validate() {

             Object.keys(this.errors).forEach(k => this.errors[k] = false);
             let valid = true;

            if (!this.form.norma) {
            this.errors.norma = true;
            valid = false;
            }

            if (!this.form.fecha_publicacion) {
            this.errors.fecha_publicacion = true;
            valid = false;
            }

            if (!this.form.equipo) {
            this.errors.equipo = true;
            valid = false;
            }

             if (!this.form.link) {
            this.errors.link = true;
            valid = false;
            }

             return valid;
        },

        async guardarNormatividad() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

           const res = await this.createAction({

                url: '/sgm/normatividad-aplicable-mediciones/create-inventario',
                data: this.form,
                table: '#table-inventario-normatividad'


            });

            if (res && res.success) {
                this.modal.hide();
            }

        }

    }));

});