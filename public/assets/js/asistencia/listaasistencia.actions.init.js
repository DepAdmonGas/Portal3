document.addEventListener('alpine:init', () => {

   Alpine.data('listaasistenciaForm', () => ({

        init() {
            const select = $('#selectPersonal').select2();
            window.listaInstance = this;
             // escuchar cambios de select2
            select.on('change', (e) => {
                this.personal = $(e.target).val() || [];
            });

            const id = document
            .getElementById('container')
            .dataset.id;
            
            if(id != 0){
            this.listar();
            }
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

        lista:[],
        archivo:null,
        error:'',
        errorArchivo: false,

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
    },

    async listar(){

    const id = document
    .getElementById('container')
    .dataset.id;
            const {data}=await axios.get(
                `/lista-asistencia-evidencia/datatble/${id}`
            );

            this.lista=data;

    },

async subir() {

    this.error = '';
    this.errorArchivo = false;

    if (!this.archivo) {
        this.error = 'Seleccione una imagen.';
        this.errorArchivo = true;
        return;
    }

    const permitidos = ['image/jpeg', 'image/png'];

    if (!permitidos.includes(this.archivo.type)) {
        this.error = 'Solo se aceptan imágenes JPG y PNG.';
        this.errorArchivo = true;
        return;
    }

    const payload = new FormData();

        const id = document
    .getElementById('container')
    .dataset.id;

    payload.append('id', id);
    payload.append('evidencia', this.archivo);

    const res = await this.createAction({
        url: `/lista-asistencia-evidencia/create`,
        data: payload
    });

    if (res.success) {

        this.archivo = null;
        this.$refs.file.value = '';

        await this.listar();
    }

},

async eliminar(id) {

    const res = await this.deleteAction({
        url: '/lista-asistencia-evidencia/delete',
        id,
        name: id
    });

    if (res && res.success) {
        await this.listar();
    }

},

        

    }));

});