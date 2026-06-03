document.addEventListener('alpine:init', () => {
Alpine.data('kpiAceitesComponent', () => ({
opciones: [
{ id: 1, titulo: 'Notas de Remisión', icono: 'ti ti-file-description' },
{ id: 2, titulo: 'Facturas', icono: 'ti ti-file-invoice' },
{ id: 3, titulo: 'Facturas Venta Mostrador', icono: 'ti ti-shopping-cart' },
{ id: 4, titulo: 'Fichas de Deposito Faltante', icono: 'ti ti-alert-triangle' },
],

data: null,
tipoCargado: false,
cargando: false,
chartMensual: null,
chartAnual: null,

async cargarTipo(tipoId) {
this.destruirGraficas();
this.cargando = true;
this.data = null;
this.tipoCargado = false;

const c = document.getElementById('kpi-aceites-container');
const idYear = c ? parseInt(c.dataset.idYear) : 0;

try {
const res = await fetch('/departamento-operativo/resumen-kpi-aceites/data/' + idYear + '/' + tipoId);
const json = await res.json();
if (json.success) {
this.data = json.data;
this.tipoCargado = true;
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

volverOpciones() {
this.destruirGraficas();
this.data = null;
this.tipoCargado = false;
},

_optsBase(tituloX, tituloY) {
return {
chart: { type: 'bar', height: 400, toolbar: { show: false } },
plotOptions: {
bar: {
columnWidth: '55%',
dataLabels: { position: 'top' },
},
},
dataLabels: {
enabled: true,
offsetY: -18,
style: { fontSize: '14px', fontWeight: 500, colors: ['#333'] },
formatter: (val) => (val > 0 ? val.toFixed(1) : ''),
},
xaxis: { title: { text: tituloX } },
yaxis: {
title: { text: tituloY },
min: 0,
labels: { formatter: (v) => v.toFixed(1) },
},
legend: { position: 'top', horizontalAlign: 'center' },
grid: { borderColor: '#e9ecef' },
};
},

renderizarGraficas() {
this.destruirGraficas();
if (!this.data || !this.data.mensual) return;

const elMensual = document.getElementById('chartMensual');
if (elMensual && this.data.mensual.categorias) {
this.chartMensual = new ApexCharts(elMensual, {
...this._optsBase('Meses', 'Puntaje Obtenido'),
series: [
{ name: 'Puntaje Total', data: this.data.mensual.maximo },
{ name: 'Puntaje Obtenido', data: this.data.mensual.obtenido },
],
xaxis: { categories: this.data.mensual.categorias, title: { text: 'Meses' } },
colors: ['#d3d3d3', '#4f81bd'],
});
this.chartMensual.render();
}

const elAnual = document.getElementById('chartAnual');
if (elAnual && this.data.anual.categorias) {
this.chartAnual = new ApexCharts(elAnual, {
...this._optsBase('Estaciones', 'Puntaje Obtenido'),
series: [
{ name: 'Puntaje Total', data: this.data.anual.maximo },
{ name: 'Puntaje Obtenido', data: this.data.anual.obtenido },
],
xaxis: { categories: this.data.anual.categorias, title: { text: 'Estaciones' } },
colors: ['#d3d3d3', '#9bbb59'],
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
