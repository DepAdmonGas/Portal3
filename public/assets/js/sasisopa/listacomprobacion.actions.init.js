document.addEventListener('alpine:init', () => {

    Alpine.data('listacomprobacionForm', () => ({

        init() {
            window.listaInstance = this;
        },

        mode: 'create',
        id: null,

        fecha: '',

        criterios: {
            R1: 'La política es adecuada a la naturaleza magnitud y actividades del proyecto',
            R2: 'La política incluye la seguridad operativa',
            R3: 'La política incluye la protección al medio ambiente',
            R4: 'Los trabajadores, la alta dirección, los clientes y los subcontratistas tienen conocimiento de la política',
            R5: 'La política se revisa periódicamente',
            R6: 'La política se compromete al control de los peligros e impactos ambientales',
            R7: 'La política considera la participación del personal'
        },

        respuestas: {
            R1: '',
            R2: '',
            R3: '',
            R4: '',
            R5: '',
            R6: '',
            R7: ''
        },

        asistentes: '',
        comentarios: '',

        loading: false,

        errors: {
            fecha: false,
            R1: false,
            R2: false,
            R3: false,
            R4: false,
            R5: false,
            R6: false,
            R7: false
        },

        validate() {

            this.errors.fecha = !this.fecha;

            Object.keys(this.respuestas).forEach(k => {
                this.errors[k] = !this.respuestas[k];
            });

            return !this.errors.fecha &&
                   Object.values(this.errors).every(e => e === false);
        },

        openEdit(data) {

            this.mode = 'edit';
            this.id = data.id;

            this.fecha = data.fecha ? data.fecha.split('T')[0] : '';

            Object.keys(this.respuestas).forEach(k => {
                this.respuestas[k] = data[k] ?? '';
            });

            this.asistentes = data.asistentes ?? '';
            this.comentarios = data.comentarios ?? '';

            const modal = new bootstrap.Modal(document.getElementById('listaComprobacion'));
            modal.show();
        },

        resetForm() {

            this.mode = 'create';
            this.id = null;
            this.fecha = '';

            Object.keys(this.respuestas).forEach(k => this.respuestas[k] = '');
            Object.keys(this.errors).forEach(k => this.errors[k] = false);

            this.asistentes = '';
            this.comentarios = '';

        },

        resetModal(){

            const modalEl = document.getElementById('listaComprobacion');

                    // evento al cerrar completamente
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        this.resetForm();
                        document.body.focus(); // opcional (mejora accesibilidad)
                    }, { once: true });

                    // quitar foco ANTES de cerrar
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }

                    const modal = bootstrap.Modal.getInstance(modalEl);

                    if (modal) {
                        modal.hide();
                    }

        },

        async submit() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

            let url = this.mode === 'create'
                ? '/sasisopa/politica/lista-comprobacion/create'
                : '/sasisopa/politica/lista-comprobacion/update';

            let payload = {
                id: this.id,
                fecha: this.fecha,
                ...this.respuestas,
                asistentes: this.asistentes,
                comentarios: this.comentarios
            };

            try {

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-lista-comprobacion'
                });

                if (res && res.success) {

                    this.resetModal();
                }

            } catch (error) {
                this.notify('error', 'Error al guardar');
            }
        },
        async getEdit(id) {

        try {

            const res = await axios.get(`/sasisopa/politica/lista-comprobacion/${id}`);

            if (res.data.success) {

                this.openEdit(res.data.data);

            } else {
                this.notify('error', res.data.message);
            }

        } catch (error) {
            this.notify('error', 'Error al obtener datos');
        }
    }

    }));

});
