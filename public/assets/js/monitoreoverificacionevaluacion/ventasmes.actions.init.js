document.addEventListener('alpine:init', () => {
    Alpine.data('ventasMes', () => ({

        year: new Date().getFullYear(),

        loading: false,

        ventas: [],

        async init() {

            await this.buscar();

        },

        async buscar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/monitoreo-verificacion-evaluacion/ventas-mes/get',
                    {
                        params: {
                            year: this.year
                        }
                    }
                );

                this.ventas = data.data ?? [];

            } catch (error) {

                console.error(error);

            } finally {

                this.loading = false;

            }

        }

        
    }));
});