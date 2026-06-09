document.addEventListener('alpine:init', () => {
    Alpine.data('tanqueAlmacenamiento', () => ({

        mode: 'create',
        id_tanque: null,

        no_tanque: '',
        capacidad: '',
        producto: '',

        errors: {
            no_tanque: false,
            capacidad: false,
            producto: false,
        },
       
        init() {

            window.tanqueAlmacenamiento = this;
            this.modalNuevo = new bootstrap.Modal(
                document.getElementById('modalNuevo')
            );

        },

          validate() {

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if (!this.no_tanque) {
                this.errors.no_tanque = true;
                valid = false;
            }

            if (!this.capacidad) {
                this.errors.capacidad = true;
                valid = false;
            }

            if (!this.producto) {
                this.errors.producto = true;
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
            this.id_tanque = null;

            this.no_tanque = '',
            this.capacidad = '',
            this.producto = '',          

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
            this.id_tanque = row.id;

            this.no_tanque = row.no_tanque ?? '';
            this.capacidad = row.capacidad ?? '';
            this.producto = row.producto ?? '';

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

                    ? '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-tanques/create'

                    : `/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-tanques/update`;

                const payload = {
                    id_tanque: this.id_tanque,
                    no_tanque: this.no_tanque,
                    capacidad: this.capacidad,
                    producto: this.producto
                };

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-tanque-almacenamiento'
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

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-tanques/delete',

                id,
                name,

                table: '#table-tanque-almacenamiento'
            });
        },


    }));
});