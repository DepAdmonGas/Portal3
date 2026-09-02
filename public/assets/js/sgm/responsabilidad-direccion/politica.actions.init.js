document.addEventListener('alpine:init', () => {

    Alpine.data('politicaForm', () => ({

        fecha: '',

        quill: null,

        async init() {

            await this.$nextTick();

            this.quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                modules: {
                    toolbar: true
                }
            });

            await this.cargar();

        },

        async cargar() {

            if (!document.getElementById('sgm-content')) {
                return;
            }

            const { data } = await axios.get(
                '/sgm/responsabilidades-direccion/politica-sgm/detalle'
            );

            this.fecha = data.fecha ?? '';

            if (!data.contenido) {
                return;
            }

            this.quill.root.innerHTML = data.contenido;

        },

        async guardar() {

            const contenido = this.quill.root.innerHTML;

            if (!this.fecha) {

                this.notify(
                    'error',
                    'Seleccione la fecha.'
                );

                return;
            }

            if (
                this.quill.getText().trim() === ''
            ) {

                this.notify(
                    'error',
                    'Capture la política.'
                );

                return;
            }

            await this.createAction({

                url: '/sgm/responsabilidades-direccion/politica-sgm/create',

                data: {
                    fecha: this.fecha,
                    contenido
                },

                onSuccess: () => {

                    history.back();

                }

            });

        }

    }));

});