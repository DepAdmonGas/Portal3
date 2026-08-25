document.addEventListener('alpine:init', () => {

    Alpine.data('sgm', () => ({

        anio: new Date().getFullYear(),

        estaciones: window.estacionesSgm ?? [],

        years: [],


        init() {

            const actual = new Date().getFullYear();

            for (let year = 2024; year <= actual; year++) {

                this.years.push(year);

            }

        },


        descargarReporte(idEstacion) {

            window.location.href =
                '/sgm/reporte/' +
                idEstacion +
                '/' +
                this.anio;

        }

    }));

});