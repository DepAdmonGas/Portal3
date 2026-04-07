document.addEventListener('alpine:init', () => {

    Alpine.data('anexosForm', () => ({

        fecha: '',
        descripcion: '',
        anexos: [],
        loading: false,

        init() {

            window.addEventListener('open-anexos', (e) => {
                this.cargarAnexos(e.detail.id);
            });

        },

        async cargarAnexos(id) {

            this.loading = true;

            try {

                const res = await axios.get('/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/analisis-riesgo-anexos/' + id);

                if (res.data.success) {

                    this.fecha = res.data.data.fecha;
                    this.descripcion = res.data.data.descripcion;
                    this.anexos = res.data.data.anexos;

                } else {
                    this.notify('error', res.data.message);
                }

            } catch (error) {
                this.notify('error', 'Error al cargar');
            } finally {
                this.loading = false;
            }
        }

    }));

});