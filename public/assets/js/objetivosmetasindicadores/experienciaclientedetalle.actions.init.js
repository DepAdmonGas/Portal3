document.addEventListener('alpine:init', () => {
    Alpine.data('experienciaClienteDetalle', () => ({

        clientes: [],
         detalle: {
            nombre: '',
            comentario: '',
            preguntas: []
        },
        preguntas: [],
        charts: [],
        loading: false,
         chart: null,

        async init() {
             await this.getExperienciaCliente();
             await this.getClientes();
             await this.getPreguntas();
        },

        async getExperienciaCliente() {
            this.loading = true;
            const id = document.getElementById('container').dataset.id;
            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/chart-experiencia-cliente?id=${id}`);
            const json = await res.json();

            this.loading = false;

            if (json.success) {
                this.renderChart(json.data);
            }

        },

        renderChart(chartData) {

            const options = {
                chart: {
                    type: 'pie',
                    height: 400
                },

                labels: chartData.labels,
                series: chartData.series,

                colors: [
                    getComputedStyle(document.documentElement).getPropertyValue('--bs-primary'),
                    getComputedStyle(document.documentElement).getPropertyValue('--bs-success'),
                    getComputedStyle(document.documentElement).getPropertyValue('--bs-warning'),
                    getComputedStyle(document.documentElement).getPropertyValue('--bs-danger')
                ],

                plotOptions: {
                    pie: {
                        dataLabels: {
                            offset: -5
                        }
                    }
                },

                dataLabels: {
                    formatter: function (val) {
                        return val.toFixed(1) + "%";
                    }
                },

                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val.toFixed(1) + "%";
                        }
                    }
                },

                legend: {
                    position: 'bottom'
                }
            };

            this.chart = new ApexCharts(document.querySelector("#chart"), options);
            this.chart.render();
        },

        dataLabels: {
            formatter: function (val, opts) {
                return opts.w.config.series[opts.seriesIndex] + "%";
            }
        },

        async getClientes() {

            const id = document.getElementById('container').dataset.id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/lista-encuesta-cliente?id=${id}`);
            const json = await res.json();

            if (json.success) {
                this.clientes = json.data;
            }
        },

        renderCharts() {

            this.preguntas.forEach((p, index) => {

                const total = Number(p.excelente) + Number(p.bueno) + Number(p.regular) + Number(p.malo);

                const series = total > 0 ? [
                Number(((p.excelente / total) * 100).toFixed(1)),
                Number(((p.bueno / total) * 100).toFixed(1)),
                Number(((p.regular / total) * 100).toFixed(1)),
                Number(((p.malo / total) * 100).toFixed(1))
            ] : [0, 0, 0, 0];

                const options = {
                    chart: {
                        type: 'pie',
                        height: 350
                    },
                    labels: ['Excelente (' + p.excelente + ')', 'Bueno (' + p.bueno + ')', 'Regular (' + p.regular + ')', 'Malo (' + p.malo + ')'],
                    series: series,
                    colors: [
                        getComputedStyle(document.documentElement).getPropertyValue('--bs-primary'),
                        getComputedStyle(document.documentElement).getPropertyValue('--bs-success'),
                        getComputedStyle(document.documentElement).getPropertyValue('--bs-warning'),
                        getComputedStyle(document.documentElement).getPropertyValue('--bs-danger')
                    ],
                    dataLabels: {
                       formatter: function (val) {
                        return val.toFixed(1) + "%";
                    }
                    },
                     tooltip: {
                        y: {
                            formatter: function (val) {
                                return val.toFixed(1) + "%";
                            }
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                };

                const chart = new ApexCharts(
                    document.querySelector(`#chartPregunta${p.id}`),
                    options
                );

                chart.render();

                this.charts.push(chart);
            });
        },

        async getPreguntas() {

             const id = document.getElementById('container').dataset.id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/chart-experiencia-cliente-preguntas?id=${id}`);
            const json = await res.json();

            if (json.success) {
                this.preguntas = json.data;

                this.$nextTick(() => {
                    this.renderCharts();
                });
            }

        },

        async Detalle(id) {

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/detalle-encuesta-cliente?id=${id}`);
            const json = await res.json();

            if (json.success) {

                this.detalle = json.data;

                const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
                modal.show();
            }
        },

        getColor(resultado) {
            switch (parseInt(resultado)) {
                case 4: return '#0099F0';
                case 3: return '#1EAD4E';
                case 2: return '#F3C000';
                case 1: return '#E70606';
                default: return '#000';
            }
        },

        getTexto(resultado) {
            switch (parseInt(resultado)) {
                case 4: return 'Excelente';
                case 3: return 'Bueno';
                case 2: return 'Regular';
                case 1: return 'Malo';
                default: return '';
            }
        },

             

    }));
});