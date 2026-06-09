document.addEventListener('alpine:init', () => {
    Alpine.data('sondasMedicion', () => ({

        mode: 'create',
        modalNuevo: null,
        id_sonda: null,

        no_sonda: '',
        marca: '',
        modelo: '',
        ubicacion: '',

        errors: {
            no_sonda: false,
            marca: false,
            modelo: false,
            ubicacion: false,
        },
       
        init() {

            window.sondasMedicion = this;
            this.modalNuevo = new bootstrap.Modal(
                document.getElementById('modalNuevo')
            );

        },

          validate() {

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if (!this.no_sonda) {
                this.errors.no_sonda = true;
                valid = false;
            }

            if (!this.marca) {
                this.errors.marca = true;
                valid = false;
            }

            if (!this.modelo) {
                this.errors.modelo = true;
                valid = false;
            }

            if (!this.ubicacion) {
                this.errors.ubicacion = true;
                valid = false;
            }

            return valid;
            
            },

             closeModal() {

            if (this.modalNuevo) {
                this.modalNuevo.hide();
            }
        },

         limpiar() {

            this.mode = 'create';
            this.id_sonda = null;

            this.no_sonda = '',
            this.marca = '',
            this.modelo = '',
            this.ubicacion = '',

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);
        },

         modalopen() {

            this.limpiar();
            this.modalNuevo.show();
        },

        openModalEditar(row) {

            this.limpiar();
            this.mode = 'edit';
            this.id_sonda = row.id;

            this.no_sonda = row.no_sonda ?? '';
            this.marca = row.marca ?? '';
            this.modelo = row.modelo ?? '';
            this.ubicacion = row.ubicacion ?? '';

            this.modalNuevo.show();
        },

         async guardar() {

            if (!this.validate()) {

                this.notify(
                    'error',
                    'Completa todos los campos'
                );

                return;
            }

            try {

                const url = this.mode === 'create'

                    ? '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/create'

                    : `/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/update`;

                const payload = {
                    id_sonda: this.id_sonda,
                    no_sonda: this.no_sonda,
                    marca: this.marca,
                    modelo: this.modelo,
                    ubicacion: this.ubicacion
                };

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-sondas-medicion'
                });

                if (res && res.success) {

                    this.modalNuevo.hide();

                    this.limpiar();
                }

            } catch (e) {

                this.notify(
                    'error',
                    'Error al guardar'
                );
            }
        },


        async eliminar(id, name) {

            await this.deleteAction({

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion/delete',

                id,
                name,

                table: '#table-sondas-medicion'
            });
        },


    }));
});