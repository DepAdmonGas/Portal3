document.addEventListener('alpine:init', () => {
    Alpine.data('indicadorVentas', () => ({

        year: new Date().getFullYear(),
        ventas: [],
        totales: {},
        productos: {},
        loading: false,

        async init() {
            await this.getVentas();
        },

        async getVentas() {
        this.loading = true;

        try {
            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/get-indicador-ventas?year=${this.year}`);
            const data = await res.json();

            if (!data.success) return;

            // TABLA
            this.ventas = data.data;
            this.totales = data.totales;
            this.productos = data.productos;

            // GRAFICA
            this.renderChart(data.chart);

            history.replaceState(null, '', `?year=${this.year}`);

        } catch (e) {
            this.notify('error', 'Error al obtener los datos');
        }

        this.loading = false;
    },

    renderChart(chartData) {

        const options = {
            chart: {
                type: 'bar',
                height: 400,
                toolbar: {
                    show: true
                },
                defaultLocale: 'es',
                locales: [{
                    name: 'es',
                    options: {
                        toolbar: {
                        exportToSVG: "Descargar SVG",
                        exportToPNG: "Descargar PNG",
                        exportToCSV: "Descargar CSV",
                        menu: "Menú",
                        selection: "Seleccionar",
                        selectionZoom: "Zoom por selección",
                        zoomIn: "Acercar",
                        zoomOut: "Alejar",
                        pan: "Mover",
                        reset: "Restablecer Zoom"
                        }
                }
                }]
            },
            series: chartData.series,
            xaxis: {
                categories: chartData.categories
            },
            colors: ['#78bd24', '#e01483', '#5e0f8e'],
            dataLabels: {
                enabled: false
            }
        };

        if (this.chart) {
            this.chart.destroy();
        }

        this.chart = new ApexCharts(document.querySelector("#chart"), options);
        this.chart.render();
    },

        changeYear(e) {
            this.year = e.target.value;
            this.getVentas();
        },
        format(num) {
            return (Math.round((num || 0) * 100) / 100).toLocaleString('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
       

    }));
});