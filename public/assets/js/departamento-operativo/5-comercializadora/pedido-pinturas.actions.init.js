document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasComponent', () => ({

puedeAcceso: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esEncargado: false,
esMultiestacion: false,
idEstacion: 0,
idUsuario: 0,
hayContexto: false,

pedidoActual: { id: 0, detalle: [], status: 0 },

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',
BASE_FIRMA: '/departamento-operativo/comercializadora/pedido-pinturas-firma',

async _confirmarEliminar(nombre) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El registro: ' + nombre + ' será eliminado',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
return result.isConfirmed;
},

async _eliminar(url, data, nombre) {
if (this.loading) return;
const ok = await this._confirmarEliminar(nombre || 'Registro');
if (!ok) return null;

this.loading = true;
try {
const resp = await axios.post(url, data);
const res = resp.data;
this.handleResponse(resp);
if (res && res.success) {
if (window.tablaPedidos) window.tablaPedidos.ajax.reload(null, false);
await this.actualizarPendientes();
}
return res;
} catch (err) {
const mensaje = err.response?.data?.message || 'Error al eliminar';
this.showAlert('error', 'Error', mensaje);
this.notify('error', mensaje);
return null;
} finally {
this.loading = false;
}
},

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeFirmar = c.dataset.puedeFirmar === 'true';
this.esEncargado = c.dataset.esEncargado === 'true';
this.esMultiestacion = c.dataset.multiestacion === 'true';
this.idEstacion = parseInt(c.dataset.idEstacion || '0');
this.idUsuario = parseInt(c.dataset.idUsuario || '0');

window.pedidoPinturasComponentInstance = this;

this.actualizarPendientes();
this.actualizarHayContexto();

document.addEventListener('pedido-pinturas:estacion-cambio', () => {
this.actualizarPendientes();
this.actualizarHayContexto();
});
},

getSelector() {
return document.getElementById('module-station-selector-pedido-pinturas');
},

getIdEstacionContexto() {
const sel = this.getSelector();
if (sel && sel.value) {
const p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return this.idEstacion || 0;
},

esTodasEstaciones() {
const sel = this.getSelector();
return sel && sel.value === '';
},

actualizarHayContexto() {
this.hayContexto = this.getIdEstacionContexto() > 0 && !this.esTodasEstaciones();
},

firmaUrl(id) {
return this.BASE_FIRMA + '/' + id;
},

async actualizarPendientes() {
const badge = document.getElementById('pedidos-pendientes-badge');
if (!badge) return;
try {
const resp = await fetch(this.BASE + '/get-pendientes');
const json = await resp.json();
if (!json.success) return;

const total = parseInt(json.total || 0, 10);
const contexto = parseInt(json.contexto !== undefined ? json.contexto : total, 10);
const totalEl = document.getElementById('pedidos-pendientes-total');
if (totalEl) totalEl.textContent = contexto;

const sel = this.getSelector();
if (sel) {
Array.prototype.forEach.call(sel.options, function (opt) {
if (!opt.value) return;
const key = opt.value;
const base = (opt.textContent || '').replace(/\s*\(\d+\)\s*$/, '').trim();
const count = parseInt(json[key] || 0, 10);
opt.textContent = base + ' (' + count + ')';
});
const first = sel.options[0];
if (first && first.value === '') {
const base = (first.textContent || '').replace(/\s*\(\d+\)\s*$/, '').trim();
first.textContent = base + ' (' + total + ')';
}
}
} catch (e) {
console.error('Error actualizando pendientes:', e);
}
},

/* ================= PEDIDOS ================= */

async nuevoPedido() {
if (this.guardandoProducto) return;

const idEstacion = this.getIdEstacionContexto();
if (!idEstacion || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación específica para crear un pedido.');
return;
}

this.guardandoProducto = true;
try {
const fd = new FormData();
fd.append('id_estacion', idEstacion);
const resp = await axios.post(this.BASE + '/crear', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Pedido creado correctamente');
setTimeout(() => { window.location.href = this.BASE + '/' + json.id; }, 800);
} else {
if (window.Notify) Notify.error(json.message || 'Error al crear el pedido');
}
} catch (e) {
console.error('Error creando pedido:', e);
if (window.Notify) Notify.error('Error al crear el pedido');
} finally {
this.guardandoProducto = false;
}
},

async abrirDetallePedido(id) {
try {
const resp = await axios.get(this.BASE + '/detalle?id=' + id);
const json = resp.data;
if (!json.success) {
if (window.Notify) Notify.error('Error al cargar el detalle');
return;
}
this.pedidoActual = json.pedido;
const m = document.getElementById('modalDetallePedido');
bootstrap.Modal.getOrCreateInstance(m).show();
} catch (e) {
console.error('Error cargando detalle:', e);
if (window.Notify) Notify.error('Error al cargar el detalle');
}
},

descargarPdf(id) {
window.open(this.BASE + '/pdf/' + id, '_blank');
},

async eliminarPedido(id, nombre) {
const res = await this._eliminar(this.BASE + '/eliminar', { id: id }, nombre);
if (res && res.success) {
await this.actualizarPendientes();
}
},

/* ================= UTILIDADES DE PRESENTACIÓN ================= */

statusBadgeClass(status) {
if (status === 0) return 'bg-danger';
if (status === 1) return 'bg-warning-subtle text-warning-emphasis';
if (status === 2) return 'bg-success-subtle text-success-emphasis';
return 'bg-secondary';
},

}));

});