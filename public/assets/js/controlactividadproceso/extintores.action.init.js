document.addEventListener('alpine:init', () => {

    Alpine.data('extintores', () => ({

        mode: 'create',
        id_extintor: null,

        modalNuevo: null,

        no_extintor: '',
        ubicacion: '',
        fecha_recarga: '',
        tipo_extintor: '',
        peso_kg: '',

        errors: {
            no_extintor: false,
            ubicacion: false,
            fecha_recarga: false,
            tipo_extintor: false,
            peso_kg: false
        },

        init() {

            window.extintores = this;

            this.modalNuevo = new bootstrap.Modal(
                document.getElementById('modalNuevo')
            );
        },

        validate() {

            let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if (!this.no_extintor) {
                this.errors.no_extintor = true;
                valid = false;
            }

            if (!this.ubicacion) {
                this.errors.ubicacion = true;
                valid = false;
            }

            if (!this.fecha_recarga) {
                this.errors.fecha_recarga = true;
                valid = false;
            }

            if (!this.tipo_extintor) {
                this.errors.tipo_extintor = true;
                valid = false;
            }

            if (!this.peso_kg) {
                this.errors.peso_kg = true;
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
            this.id_extintor = null;

            this.no_extintor = '';
            this.ubicacion = '';
            this.fecha_recarga = '';
            this.tipo_extintor = '';
            this.peso_kg = '';

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
            this.id_extintor = row.id;

            this.no_extintor = row.no_extintor ?? '';
            this.ubicacion = row.ubicacion ?? '';
            this.fecha_recarga = row.ultima_recarga ?? '';
            this.tipo_extintor = row.tipo_extintor ?? '';
            this.peso_kg = row.peso_kg ?? '';

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

                    ? '/sasisopa/control-actividades-procesos/extintores/create'

                    : `/sasisopa/control-actividades-procesos/extintores/update/${this.id_extintor}`;

                const payload = {

                    no_extintor: this.no_extintor,
                    ubicacion: this.ubicacion,
                    fecha_recarga: this.fecha_recarga,
                    tipo_extintor: this.tipo_extintor,
                    peso_kg: this.peso_kg
                };

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-extintores'
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

                url: '/sasisopa/control-actividades-procesos/extintores/delete',

                id,
                name,

                table: '#table-extintores'
            });
        },

    }));
});