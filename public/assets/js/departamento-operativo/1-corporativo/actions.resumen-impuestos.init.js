document.addEventListener('alpine:init', () => {
Alpine.data('resumenImpuestosComponent', () => ({
cargando: false,
detalleData: null,
detalleFecha: '',
detalleCargando: false,
totalesData: null,
totalesCargando: false,

init() {
document.addEventListener('abrir-detalle-impuesto', (e) => {
this.abrirDetalle(e.detail.idDia, e.detail.fecha);
});
},

formatNum(val) {
return parseFloat(val || 0).toLocaleString('en-US', {
minimumFractionDigits: 2,
maximumFractionDigits: 4
});
},

async abrirDetalle(idDia, fecha) {
this.detalleData = null;
this.detalleFecha = fecha;
this.detalleCargando = true;

const el = document.getElementById('modalDetalle');
if (!el) return;
const modal = bootstrap.Modal.getOrCreateInstance(el);
modal.show();

try {
const res = await fetch('/departamento-operativo/resumen-impuestos/detalle-dia/' + idDia);
const json = await res.json();
if (json.success) {
this.detalleData = json.data;
} else {
Notify['error'](json.message || 'Error al cargar detalle');
}
} catch (err) {
Notify['error']('Error de conexión al cargar detalle');
} finally {
this.detalleCargando = false;
}
},

async abrirTotales() {
this.totalesData = null;
this.totalesCargando = true;

const c = document.getElementById('resumen-impuestos-container');
const idYear = c ? parseInt(c.dataset.idYear) : 0;
const idMes = c ? parseInt(c.dataset.idMes) : 0;

const el = document.getElementById('modalTotales');
if (!el) return;
const modal = bootstrap.Modal.getOrCreateInstance(el);
modal.show();

try {
const res = await fetch('/departamento-operativo/resumen-impuestos/totales/' + idYear + '/' + idMes);
const json = await res.json();
if (json.success) {
this.totalesData = json.totales;
} else {
Notify['error'](json.message || 'Error al cargar totales');
}
} catch (err) {
Notify['error']('Error de conexión al cargar totales');
} finally {
this.totalesCargando = false;
}
},
}));
});
