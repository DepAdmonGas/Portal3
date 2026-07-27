document.addEventListener('alpine:init', () => {
Alpine.data('kpiPersonalComponent', () => ({
opciones: [],
data: null,
tipoCargado: false,
cargando: false,
tipoSeleccionado: null,
chartMensual: null,
chartAnual: null,

init() {
const el = document.getElementById('kpi-personal-container');
this.opciones = el ? JSON.parse(el.dataset.opciones || '[]') : [];

if (typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init('control-documentos-personal', {
customReload: () => { window.location.reload(); }
});
}

const storedTipo = sessionStorage.getItem('kpi_personal_tipo');
if (storedTipo) {
this.cargarTipo(parseInt(storedTipo));
}
},

async cargarTipo(tipoId) {
this.destruirGraficas();
this.cargando = true;
this.data = null;
this.tipoCargado = false;
this.tipoSeleccionado = tipoId;
sessionStorage.setItem('kpi_personal_tipo', tipoId);

const el = document.getElementById('kpi-personal-container');
const idYear = el ? parseInt(el.dataset.idYear) : 0;

try {
const res = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal-kpi-data/' + idYear + '/' + tipoId);
const json = await res.json();
if (json.success) {
this.data = json.data;
this.tipoCargado = true;
this.$nextTick(() => this.renderizarGraficas());
} else {
Notify['error'](json.message || 'Error al cargar datos');
this.tipoSeleccionado = null;
sessionStorage.removeItem('kpi_personal_tipo');
}
} catch (err) {
Notify['error']('Error de conexión al cargar evaluación');
this.tipoSeleccionado = null;
sessionStorage.removeItem('kpi_personal_tipo');
} finally {
this.cargando = false;
}
},

volverOpciones() {
this.destruirGraficas();
this.data = null;
this.tipoCargado = false;
this.tipoSeleccionado = null;
sessionStorage.removeItem('kpi_personal_tipo');
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
formatter: (val) => (val > 0 ? val : ''),
},
xaxis: { title: { text: tituloX } },
yaxis: {
title: { text: tituloY },
min: 0,
labels: { formatter: (v) => v },
},
legend: { position: 'top', horizontalAlign: 'center' },
grid: { borderColor: '#e9ecef' },
};
},

renderizarGraficas() {
this.destruirGraficas();
if (!this.data) return;

const elMensual = document.getElementById('chartMensual');
if (elMensual && this.data.mensual && this.data.mensual.categorias) {
const colores = this.data.colores_mensual || ['#4f81bd'];
this.chartMensual = new ApexCharts(elMensual, {
...this._optsBase('Meses', 'Registros'),
series: [{
name: this.data.nombre_tipo,
data: this.data.mensual.obtenido,
}],
xaxis: { categories: this.data.mensual.categorias, title: { text: 'Meses' } },
colors: colores.slice(0, this.data.mensual.categorias.length),
});
this.chartMensual.render();
}

const elAnual = document.getElementById('chartAnual');
if (elAnual && this.data.anual && this.data.anual.categorias) {
const colores = this.data.colores_estaciones || ['#3366cc'];
this.chartAnual = new ApexCharts(elAnual, {
...this._optsBase('Estaciones', 'Registros'),
series: [{
name: this.data.nombre_tipo,
data: this.data.anual.obtenido,
}],
xaxis: { categories: this.data.anual.categorias, title: { text: 'Estaciones' } },
colors: colores.slice(0, this.data.anual.categorias.length),
});
this.chartAnual.render();
}
},

destruirGraficas() {
if (this.chartMensual) { this.chartMensual.destroy(); this.chartMensual = null; }
if (this.chartAnual) { this.chartAnual.destroy(); this.chartAnual = null; }
},
}));
});
