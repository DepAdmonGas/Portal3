document.addEventListener('alpine:init', () => {
    Alpine.data('kpiRecibosNominaComponent', () => ({
        data: null,
        cargando: true,
        graficas: [],

        init() {
            this.cargarData();
        },

        async cargarData() {
            const c = document.getElementById('kpi-recibos-nomina-container');
            const idYear = c ? parseInt(c.dataset.idYear) : 0;
            const idMes = c ? parseInt(c.dataset.idMes) : 0;

            try {
                const res = await fetch('/departamento-operativo/recursos-humanos/recibos-nomina-evaluacion/data/' + idYear + '/' + idMes);
                const json = await res.json();
                if (json.success) {
                    this.data = json;
                    this.$nextTick(() => {
                        this.$nextTick(() => {
                            setTimeout(() => this.renderizarGraficas(), 60);
                        });
                    });
                } else {
                    Notify['error'](json.message || 'Error al cargar la evaluación');
                }
            } catch (err) {
                Notify['error']('Error de conexión al cargar la evaluación');
            } finally {
                this.cargando = false;
            }
        },

        _optsBase(tituloX, tituloY) {
            return {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false },
                    events: {
                        mounted: (chart, config) => {
                            const barras = chart.w.globals.seriesCollapsed;
                            if (barras && barras.length) {
                                chart.updateSeries(barras, false);
                            }
                        }
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '45%',
                        borderRadius: 2
                    }
                },
                dataLabels: {
                    enabled: true,
                    offsetY: -18,
                    style: { fontSize: '13px', fontWeight: 500, colors: ['#333'] },
                    formatter: (val) => (val > 0 ? parseInt(val).toFixed(0) : '')
                },
                xaxis: { title: { text: tituloX } },
                yaxis: {
                    title: { text: tituloY },
                    min: 0,
                    labels: { formatter: (v) => parseFloat(v).toFixed(0) }
                },
                legend: { show: true, position: 'bottom' },
                grid: { borderColor: '#e9ecef' }
            };
        },

        _crearGrafica(el, categorias, total, obtenido, tituloY) {
            if (!el) return;

            const chart = new ApexCharts(el, {
                ...this._optsBase('Actividades', tituloY),
                series: [
                    { name: 'Puntaje Total', data: total },
                    { name: 'Puntaje Obtenido', data: obtenido }
                ],
                xaxis: { categories: categorias, title: { text: 'Actividades' } },
                colors: [
                    (this.data?.colores?.total) || '#0d6efd',
                    (this.data?.colores?.obtenido) || '#198754'
                ]
            });

            chart.render();
            this.graficas.push(chart);
        },

        renderizarGraficas() {
            this.destruirGraficas();
            if (!this.data) return;

            if (this.data.es_anual && this.data.anual) {
                this._crearGrafica(
                    document.getElementById('chartAnual'),
                    this.data.anual.categorias,
                    this.data.anual.total,
                    this.data.anual.obtenido,
                    'Puntaje'
                );
                return;
            }

            if (Array.isArray(this.data.periodos)) {
                this.data.periodos.forEach(p => {
                    this._crearGrafica(
                        document.getElementById('chartPeriodo' + p.periodo),
                        p.grafica.categorias,
                        p.grafica.total,
                        p.grafica.obtenido,
                        'Puntaje'
                    );
                });
            }

            if (this.data.mensual) {
                this._crearGrafica(
                    document.getElementById('chartMensual'),
                    this.data.mensual.categorias,
                    this.data.mensual.total,
                    this.data.mensual.obtenido,
                    'Puntaje'
                );
            }
        },

        destruirGraficas() {
            this.graficas.forEach(c => {
                try {
                    c.destroy();
                } catch (e) {
                    // sin impacto
                }
            });
            this.graficas = [];
        },

        abrirInfoEvaluacion() {
            const el = document.getElementById('modalInfoEvaluacion');
            if (el) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            }
        }
    }));
});
