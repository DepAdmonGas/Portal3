document.addEventListener('alpine:init', () => {
    Alpine.data('jarraPatron', () => ({

        mode: 'create',
        modalNuevo: null,
        id_jarra: null,

        marca: '',
        no_serie: '',
        capacidad: '',
        material: '',

        errors: {
            marca: false,
            no_serie: false,
            capacidad: false,
            material: false,
        },
       
        init() {

            window.jarraPatron = this;
            this.modalNuevo = new bootstrap.Modal(
                document.getElementById('modalNuevo')
            );

        },

          validate() {

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if (!this.no_serie) {
                this.errors.no_serie = true;
                valid = false;
            }

            if (!this.marca) {
                this.errors.marca = true;
                valid = false;
            }

            if (!this.capacidad) {
                this.errors.capacidad = true;
                valid = false;
            }

            if (!this.material) {
                this.errors.material = true;
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
            this.id_jarra = null;

            this.no_serie = '',
            this.marca = '',
            this.capacidad = '',
            this.material = '',

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
            this.id_jarra = row.id;

            this.no_serie = row.no_serie ?? '';
            this.marca = row.marca ?? '';
            this.capacidad = row.capacidad ?? '';
            this.material = row.material ?? '';

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

                    ? '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/create'

                    : `/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/update`;

                const payload = {
                    id_jarra: this.id_jarra,
                    no_serie: this.no_serie,
                    marca: this.marca,
                    capacidad: this.capacidad,
                    material: this.material
                };

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-jarra-patron'
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

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/delete',
                id,
                name,
                table: '#table-jarra-patron'
            });
        },


    }));
});