document.addEventListener('alpine:init', () => {

Alpine.data('concentradoVentasComponent', () => ({
loading: true,
error: null,
year: null,
mes: null,
numProductos: 0,
daily: [],
totales: {},
puedeDescargar: false,

init() {
const container = document.getElementById('container');
if (!container) return;
this.year = container.dataset.idYear || null;
this.mes = container.dataset.idMes || null;
this.numProductos = parseInt(container.dataset.numProductos) || 0;
this.puedeDescargar = container.dataset.puedeDescargar === 'true';
this.cargarDatos();
},

async cargarDatos() {
this.loading = true;
this.error = null;
try {
const resp = await fetch('/departamento-operativo/concentrado-ventas/data/' + this.year + '/' + this.mes);
if (!resp.ok) throw new Error('Error al cargar datos');
const json = await resp.json();
this.daily = json.daily || [];
this.totales = json.totales || {};
} catch (e) {
this.error = e.message || 'Error de conexión';
} finally {
this.loading = false;
}
},

getCelda(row, prod, campo) {
return row.productos?.[prod]?.[campo] ?? 0;
},

totalProd(prod, campo) {
return this.totales?.[prod]?.[campo] ?? 0;
},

formatear(valor) {
if (valor === null || valor === undefined || isNaN(valor)) return '0.00';
return Number(valor).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},
}));

});
