document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacionPersonal', () => ({

        year: new Date().getFullYear(),
        resumen: {
            modulos: [],
            totales: {},
            externa: {}
        },
        loading: false,

        init() {
            this.getResumen();
        },

        async getResumen() {
            this.loading = true;

            try {
                const res = await fetch(`/sasisopa/objetivos-metas-indicadores/resumen-capacitacion-personal?year=${this.year}`);
                const data = await res.json();

                this.resumen = data;

                history.replaceState(null, '', `?year=${this.year}`);

            } catch (e) {
               this.notify('error', 'Error al obtener los datos');
                return;
            }

            this.loading = false;
        },

        changeYear(e) {
            this.year = e.target.value;
            this.getResumen();
        },

        porcentaje(val) {
            return `${val ?? 0}%`;
        }

    }));
});