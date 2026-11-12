document.addEventListener('alpine:init', () => {

    Alpine.data('procedimientosDoComponent', () => ({

        modulo: '',
        moduloNombre: '',

        form: {
            fecha: '',
            titulo: ''
        },

        errores: {
            fecha: '',
            titulo: '',
            documento: ''
        },

        detalle: {
            id: null,
            titulo: '',
            fecha: '',
            archivo: '',
            archivo_disponible: false,
            downloadUrl: '#',
            previewUrl: '',
            previsualizable: false,
            mensajeArchivo: ''
        },

        modalAgregar: null,
        modalDetalle: null,

        init() {
            this.modulo = this.$el.dataset.modulo || '';
            this.moduloNombre = this.$el.dataset.moduloNombre || '';

            const modalAgregar = document.getElementById('modalProcedimiento');
            if (modalAgregar) {
                this.modalAgregar = bootstrap.Modal.getOrCreateInstance(modalAgregar);
            }

            const modalDetalle = document.getElementById('modalProcedimientoDetalle');
            if (modalDetalle) {
                this.modalDetalle = bootstrap.Modal.getOrCreateInstance(modalDetalle);
            }
        },

        abrirAgregar() {
            this.form.fecha = '';
            this.form.titulo = '';
            this.errores = { fecha: '', titulo: '', documento: '' };

            if (this.$refs.documento) {
                this.$refs.documento.value = '';
            }

            if (this.modalAgregar) {
                this.modalAgregar.show();
            }
        },

        async guardar() {
            const archivo = this.$refs.documento && this.$refs.documento.files[0];
            this.errores = { fecha: '', titulo: '', documento: '' };

            if (!this.form.fecha) {
                this.errores.fecha = 'La fecha es obligatoria.';
            }

            if (!this.form.titulo.trim()) {
                this.errores.titulo = 'El título es obligatorio.';
            } else if (this.form.titulo.trim().length > 500) {
                this.errores.titulo = 'El título no debe exceder 500 caracteres.';
            }

            if (!archivo) {
                this.errores.documento = 'Debe seleccionar un archivo.';
            }

            if (this.errores.fecha || this.errores.titulo || this.errores.documento) {
                this.notify('error', 'Completa los campos obligatorios antes de guardar.');
                return;
            }

            const datos = new FormData();
            datos.append('fecha', this.form.fecha);
            datos.append('titulo', this.form.titulo);
            datos.append('documento', archivo);

            const respuesta = await this.createAction({
                url: '/departamento-operativo/procedimientos/' + this.modulo + '/create',
                data: datos,
                table: '#tabla-procedimientos-do'
            });

            if (respuesta && respuesta.success) {
                if (this.modalAgregar) {
                    this.modalAgregar.hide();
                }
                this.abrirAgregar();
            }
        },

        verDetalle(fila) {
            if (!fila) return;

            this.detalle = this.construirDetalle(fila);

            if (this.modalDetalle) {
                this.modalDetalle.show();
            }
        },

        construirDetalle(fila) {
            const archivo = fila.archivo || '';
            const disponible = fila.archivo_disponible === true && archivo !== '';
            const extension = disponible ? archivo.split('.').pop().toLowerCase() : '';
            const previsualizable = disponible && ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(extension) !== -1;
            const base = '/download?tipo=procedimientos-modulos&file=' + encodeURIComponent(archivo);

            return {
                id: fila.id || null,
                titulo: fila.titulo || '',
                fecha: fila.fecha || '',
                archivo: archivo,
                archivo_disponible: disponible,
                downloadUrl: disponible ? base : '#',
                previewUrl: previsualizable ? base + '&view=1' : '',
                previsualizable: previsualizable,
                mensajeArchivo: disponible
                    ? 'El documento no se puede previsualizar. Descárguelo para consultarlo.'
                    : 'El archivo no está disponible.'
            };
        },

        async eliminar(fila) {
            if (!fila || !fila.id) return;

            const respuesta = await this.deleteAction({
                url: '/departamento-operativo/procedimientos/delete',
                id: fila.id,
                name: fila.titulo || 'Procedimiento',
                table: '#tabla-procedimientos-do'
            });

            if (respuesta && respuesta.success && this.modalDetalle) {
                this.modalDetalle.hide();
            }
        }

    }));

});
