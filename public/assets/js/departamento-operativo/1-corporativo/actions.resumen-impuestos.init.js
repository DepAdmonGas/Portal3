document.addEventListener('alpine:init', () => {
Alpine.data('resumenImpuestosComponent', () => ({
cargando: false,
detalleData: null,
detalleFecha: '',
detalleCargando: false,
selectedDetailProduct: null,
totalesData: null,
totalesCargando: false,
selectedTotalProduct: null,
modalTriggers: {},

init() {
document.addEventListener('abrir-detalle-impuesto', (e) => {
this.abrirDetalle(e.detail.idDia, e.detail.fecha, e.detail.trigger);
});
for (const [id, property] of [['modalDetalle', 'detalleData'], ['modalTotales', 'totalesData']]) {
const modal = document.getElementById(id);
if (!modal) continue;
modal.addEventListener('hide.bs.modal', () => {
const active = document.activeElement;
if (active && modal.contains(active) && typeof active.blur === 'function') active.blur();
});
modal.addEventListener('hidden.bs.modal', () => {
this[property] = null;
const trigger = this.modalTriggers[id];
if (trigger?.isConnected && typeof trigger.focus === 'function') trigger.focus();
delete this.modalTriggers[id];
});
}
},

formatNum(val) {
return parseFloat(val || 0).toLocaleString('en-US', {
minimumFractionDigits: 2,
maximumFractionDigits: 4
});
},

async abrirDetalle(idDia, fecha, trigger) {
this.detalleData = null;
this.detalleFecha = fecha;
this.detalleCargando = true;
this.selectedDetailProduct = null;

const el = document.getElementById('modalDetalle');
if (!el) return;
this.modalTriggers.modalDetalle = trigger || document.activeElement;
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

async abrirTotales(event) {
this.totalesData = null;
this.totalesCargando = true;
this.selectedTotalProduct = null;

const c = document.getElementById('resumen-impuestos-container');
const idYear = c ? parseInt(c.dataset.idYear) : 0;
const idMes = c ? parseInt(c.dataset.idMes) : 0;

const el = document.getElementById('modalTotales');
if (!el) return;
this.modalTriggers.modalTotales = event?.currentTarget || document.activeElement;
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
