document.addEventListener('alpine:init', () => {

    Alpine.data('objetivosForm', () => ({

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

    const { data } = await axios.get(
        '/sgm/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/detalle'
    );

    if (!data.detalle) {
        return;
    }

    this.quill.root.innerHTML = data.detalle;

},

        async guardar() {

            const detalle = this.quill.root.innerHTML;

            if (
                detalle === '<p><br></p>' ||
                detalle.trim() === ''
            ) {
                this.notify('error', 'Capture la política');
                return;
            }

            await this.createAction({

                url: '/sgm/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/create',

                data: {
                    detalle: detalle
                },

                onSuccess: () => {
                    history.back();
                }

            });

        }

    }));

});