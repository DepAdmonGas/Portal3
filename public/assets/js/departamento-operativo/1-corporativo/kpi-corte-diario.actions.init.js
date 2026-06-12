document.addEventListener('alpine:init', () => {
Alpine.data('kpiCorteDiarioComponent', () => ({
data: null,
cargando: true,
chartMensual: null,
chartAnual: null,

init() {
this.cargarData();
},

async cargarData() {
const c = document.getElementById('kpi-corte-diario-container');
const idYear = c ? parseInt(c.dataset.idYear) : 0;
const idMes = c ? parseInt(c.dataset.idMes) : 0;

try {
const res = await fetch('/departamento-operativo/corte-diario-evaluacion/data/' + idYear + '/' + idMes);
const json = await res.json();
if (json.success) {
this.data = json.data;
this.$nextTick(() => this.renderizarGraficas());
} else {
Notify['error'](json.message || 'Error al cargar datos');
}
} catch (err) {
Notify['error']('Error de conexión al cargar evaluación');
} finally {
this.cargando = false;
}
},

_optsBase(tituloX, tituloY) {
return {
chart: { type: 'bar', height: 400, toolbar: { show: false } },
plotOptions: {
bar: {
distributed: true,
columnWidth: '55%',
dataLabels: { position: 'top' },
},
},
dataLabels: {
enabled: true,
offsetY: -18,
style: { fontSize: '14px', fontWeight: 500, colors: ['#333'] },
formatter: (val) => (val > 0 ? val.toFixed(0) : ''),
},
xaxis: { title: { text: tituloX } },
yaxis: {
title: { text: tituloY },
min: 0,
labels: { formatter: (v) => v.toFixed(0) },
},
legend: { show: false },
grid: { borderColor: '#e9ecef' },
};
},

renderizarGraficas() {
this.destruirGraficas();
if (!this.data) return;

const elMensual = document.getElementById('chartMensual');
if (elMensual && this.data.mensual) {
this.chartMensual = new ApexCharts(elMensual, {
...this._optsBase('Meses', 'No. de Aperturas'),
series: [
{ name: 'Aperturas', data: this.data.mensual.obtenido },
],
xaxis: { categories: this.data.mensual.categorias, title: { text: 'Meses' } },
colors: this.data.colores_mensual,
});
this.chartMensual.render();
}

const elAnual = document.getElementById('chartAnual');
if (elAnual && this.data.anual) {
this.chartAnual = new ApexCharts(elAnual, {
...this._optsBase('Estaciones', 'No. de Aperturas'),
series: [
{ name: 'Aperturas', data: this.data.anual.obtenido },
],
xaxis: { categories: this.data.anual.categorias, title: { text: 'Estaciones' } },
colors: this.data.colores_anual,
});
this.chartAnual.render();
}
},

destruirGraficas() {
if (this.chartMensual) { this.chartMensual.destroy(); this.chartMensual = null; }
if (this.chartAnual) { this.chartAnual.destroy(); this.chartAnual = null; }
},

abrirInfoEvaluacion() {
const el = document.getElementById('modalInfoEvaluacion');
if (el) {
const modal = new bootstrap.Modal(el);
modal.show();
}
},
}));
});
