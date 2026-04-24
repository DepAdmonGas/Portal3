document.addEventListener('alpine:init', () => {

   Alpine.data('listaasistenciaForm', () => ({

        init() {
            const select = $('#selectPersonal').select2();
            window.listaInstance = this;
             // escuchar cambios de select2
            select.on('change', (e) => {
                this.personal = $(e.target).val() || [];
            });
        },

        id: null,
        fecha: '',
        hora: '',
        lugar: '',
        encargado: '',
        tema: '',
        finalidad: '',
        personal: [],
        loading: false,

        errors: {
        fecha: false,
        hora: false,
        lugar: false,
        encargado: false,
        tema: false,
        finalidad: false
        },

        validate() {

        Object.keys(this.errors).forEach(k => this.errors[k] = false);

        let valid = true;

        if (!this.fecha) {
            this.errors.fecha = true;
            valid = false;
        }

        if (!this.hora) {
            this.errors.hora = true;
            valid = false;
        }

        if (!this.lugar) {
            this.errors.lugar = true;
            valid = false;
        }

        if (!this.encargado) {
            this.errors.encargado = true;
            valid = false;
        }

        if (!this.tema) {
            this.errors.tema = true;
            valid = false;
        }

        if (!this.finalidad) {
            this.errors.finalidad = true;
            valid = false;
        }

        return valid;
    },

        async crearAsistencia() {

            const punto = document
                .getElementById('container')
                .dataset.elemento;

            const herramienta = document
                .getElementById('container')
                .dataset.herramienta;

            const res = await this.createAction({
                url: '/lista-asistencia/create',
                data: {
                    punto_sasisopa: punto,
                    herramienta: herramienta
                },
                onSuccess: (res) => {
                    // redirección solo si todo salió bien
                    window.location.href = "/lista-asistencia/" + res.id;
                }
            });

        },

        async actualizar(id){

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos');
                return;
            }

            try {
             const res = await this.createAction({
                url: '/lista-asistencia/update',
                data: {
                    id: id,
                    fecha: this.fecha,
                    hora: this.hora,
                    lugar: this.lugar,
                    encargado: this.encargado,
                    tema: this.tema,
                    finalidad: this.finalidad
                }
            });

             if (res && res.success) {
                 window.history.back();
             }

            } catch (error) {
                this.notify('error', 'Error al guardar');
            }

        },

            async guardarPersonal(id) {

                if (!this.personal.length) {
                    this.notify('error', 'Selecciona al menos un usuario');
                    return;
                }

                const res = await this.createAction({
                    url: '/lista-asistencia-firma/create',
                    data: {
                        id_lista_asistencia: id,
                        personal: this.personal
                    },
                    table: '#table-lista-asistencia-firma'
                });

                if (res && res.success) {

                    // QUITAR DEL SELECT LOS AGREGADOS
                    this.personal.forEach(nombre => {
                        $('#selectPersonal option[value="' + nombre + '"]').remove();
                    });

                    // limpiar select2
                    $('#selectPersonal').val(null).trigger('change');
                    this.personal = [];
                }
            },

            async eliminarPersonal(idDetalle, nombre) {

        const res = await this.deleteAction({
            url: '/lista-asistencia-firma/delete',
            id: idDetalle,
            name: nombre,
            table: '#table-lista-asistencia-firma'
        });

            if (res && res.success) {

            const nombre = res.nombre;

            $('#selectPersonal').append(
                new Option(nombre, nombre, false, false)
            );

            $('#selectPersonal').trigger('change');
        }
    }

        

    }));

});