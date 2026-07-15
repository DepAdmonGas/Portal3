document.addEventListener('alpine:init', () => {

    Alpine.data('politicaForm', () => ({

        fecha: '',
        quill: null,

        async init() {

            this.quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: true
                }
            });

            await this.cargar();

        },

        async cargar() {

            const { data } = await axios.get(
                '/sgm/responsabilidades-direccion/politica-sgm/detalle'
            );

            this.fecha = data.fecha;

            this.quill.root.innerHTML = data.contenido ?? '';

        },

        async guardar() {

            const contenido = this.quill.root.innerHTML;

            if (!this.fecha) {
                this.notify('error', 'Seleccione la fecha');
                return;
            }

            if (
                contenido === '<p><br></p>' ||
                contenido.trim() === ''
            ) {
                this.notify('error', 'Capture la política');
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