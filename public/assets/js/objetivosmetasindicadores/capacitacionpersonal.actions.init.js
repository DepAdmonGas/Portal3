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
            const legacyYear = Number(new URLSearchParams(window.location.search).get('year'));
            if (Number.isInteger(legacyYear) && legacyYear >= 2020 && legacyYear <= new Date().getFullYear()) {
                this.year = legacyYear;
            }

            // Compatibilidad con enlaces antiguos; la página queda en la ruta limpia.
            if (window.location.search) {
                history.replaceState(null, document.title, window.location.pathname);
            }

            this.actualizarYearBreadcrumb();

            document.addEventListener('click', (event) => {
                const opcion = event.target.closest('[data-capacitacion-personal-year]');
                if (!opcion) return;

                event.preventDefault();
                this.cambiarYear(opcion.dataset.capacitacionPersonalYear);
            });

            this.getResumen();
        },

        cambiarYear(year) {
            const seleccionado = Number(year);
            if (!Number.isInteger(seleccionado) || seleccionado < 2020 || seleccionado > new Date().getFullYear()) return;

            this.year = seleccionado;
            this.actualizarYearBreadcrumb();
            this.getResumen();
        },

        actualizarYearBreadcrumb() {
            const etiqueta = document.getElementById('capacitacion-personal-year-label');
            if (etiqueta) etiqueta.textContent = this.year;
        },

        async getResumen() {
            this.loading = true;

            try {
                const res = await fetch(`/sasisopa/objetivos-metas-indicadores/resumen-capacitacion-personal?year=${this.year}`);
                const data = await res.json();

                this.resumen = data;

            } catch (e) {
               this.notify('error', 'Error al obtener los datos');
                return;
            }

            this.loading = false;
        },

        porcentaje(val) {
            return `${val ?? 0}%`;
        }

    }));
});
