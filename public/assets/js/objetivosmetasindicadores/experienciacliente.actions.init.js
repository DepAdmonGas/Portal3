document.addEventListener('alpine:init', () => {
    Alpine.data('experienciaCliente', () => ({

        loading: false,
         chart: null,

       async init() {
            await this.getExperienciaCliente();
        },

        async getExperienciaCliente() {
            this.loading = true;

            const res = await fetch('/sasisopa/objetivos-metas-indicadores/chart-experiencia-cliente');
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
                    height: 350
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

        async nuevo(){
            // Implementar lógica para agregar un nuevo registro

            try {
             const res = await this.createAction({
                url: '/sasisopa/objetivos-metas-indicadores/create-experiencia-cliente'
            });

            if (res && res.success) {
             window.location.href = `/sasisopa/objetivos-metas-indicadores/editar-experiencia-cliente/${res.id}`;
            }

            
            } catch (error) {
                this.notify('error', 'Error al crear');
            }

        },

        openEditar(id){
            window.location.href = `/sasisopa/objetivos-metas-indicadores/editar-experiencia-cliente/${id}`;
        },
        
        openView(id){
            window.location.href = `/sasisopa/objetivos-metas-indicadores/detalle-experiencia-cliente/${id}`;
        }

    }));
});