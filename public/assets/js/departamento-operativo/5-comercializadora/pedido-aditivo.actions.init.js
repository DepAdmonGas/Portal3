document.addEventListener('alpine:init', () => {

Alpine.data('pedidoAditivoComponent', () => ({

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

solicitudActual: { id: 0, tambos: [], status: 0, total_tambos: 0 },

pagosSolicitud: { id: 0, folio: '' },
documentos: [],
nuevoDocumentoArchivo: null,
subiendoDocumento: false,
cargandoDocumentos: false,

comentarios: [],
nuevoComentario: '',
cargandoComentarios: false,
guardandoComentario: false,
errorComentario: false,
solicitudComentariosId: 0,
solicitudComentariosFolio: '',

BASE: '/departamento-operativo/comercializadora/pedido-aditivo',

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

window.pedidoAditivoComponentInstance = this;

this.actualizarPendientes();
this.actualizarHayContexto();

document.addEventListener('pedido-aditivo:estacion-cambio', () => {
this.actualizarPendientes();
this.actualizarHayContexto();
});
},

getSelector() {
return document.getElementById('module-station-selector-pedido-aditivo');
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

/* ================= SOLICITUDES ================= */

async nuevoPedido() {
if (this.guardandoProducto) return;

const idEstacion = this.getIdEstacionContexto();
if (!idEstacion || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación específica para crear una solicitud.');
return;
}

this.guardandoProducto = true;
try {
const fd = new FormData();
fd.append('id_estacion', idEstacion);
const resp = await axios.post(this.BASE + '/crear', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Solicitud creada correctamente');
setTimeout(() => { window.location.href = this.BASE + '/' + json.id; }, 800);
} else {
if (window.Notify) Notify.error(json.message || 'Error al crear la solicitud');
}
} catch (e) {
console.error('Error creando solicitud:', e);
if (window.Notify) Notify.error('Error al crear la solicitud');
} finally {
this.guardandoProducto = false;
}
},

async abrirDetalleSolicitud(id) {
try {
const resp = await axios.get(this.BASE + '/detalle?id=' + id);
const json = resp.data;
if (!json.success) {
if (window.Notify) Notify.error('Error al cargar el detalle');
return;
}
this.solicitudActual = json.solicitud;
const m = document.getElementById('modalDetalleSolicitud');
bootstrap.Modal.getOrCreateInstance(m).show();
} catch (e) {
console.error('Error cargando detalle:', e);
if (window.Notify) Notify.error('Error al cargar el detalle');
}
},

/* ================= PAGOS / DOCUMENTOS ================= */

async abrirModalPagos(id) {
this.pagosSolicitud = { id: id, folio: 'Solicitud #00' + id };
this.documentos = [];
this.nuevoDocumentoArchivo = null;
const input = document.getElementById('inputPagosDocumento');
if (input) input.value = '';
const m = document.getElementById('modalPagosSolicitud');
bootstrap.Modal.getOrCreateInstance(m).show();
await this.cargarDocumentosPagos();
},

async cargarDocumentosPagos() {
if (!this.pagosSolicitud.id) return;
this.cargandoDocumentos = true;
try {
const resp = await axios.get(this.BASE + '/documentos?id=' + this.pagosSolicitud.id);
const json = resp.data;
this.documentos = (json.success && json.documentos) ? json.documentos : [];
} catch (e) {
console.error('Error cargando documentos:', e);
this.documentos = [];
} finally {
this.cargandoDocumentos = false;
}
},

onPagosArchivo($event) {
this.nuevoDocumentoArchivo = ($event.target.files && $event.target.files[0]) || null;
},

async subirDocumentoPagos() {
if (this.subiendoDocumento) return;

if (!this.nuevoDocumentoArchivo) {
if (window.Notify) Notify.error('Selecciona un archivo');
return;
}

this.subiendoDocumento = true;
try {
const fd = new FormData();
fd.append('id', this.pagosSolicitud.id);
fd.append('nombre', 'PAGO');
fd.append('documento', this.nuevoDocumentoArchivo);
const resp = await axios.post(this.BASE + '/subir-documento', fd);
const json = resp.data;
if (json.success) {
this.nuevoDocumentoArchivo = null;
const input = document.getElementById('inputPagosDocumento');
if (input) input.value = '';
if (window.Notify) Notify.success(json.message || 'Documento subido correctamente');
await this.cargarDocumentosPagos();
} else {
if (window.Notify) Notify.error(json.message || 'Error al subir el documento');
}
} catch (e) {
console.error('Error subiendo documento:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al subir el documento');
} finally {
this.subiendoDocumento = false;
}
},

async eliminarDocumentoPagos(id) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El documento será eliminado permanentemente',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;

try {
const resp = await axios.post(this.BASE + '/eliminar-documento', { id: id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Documento eliminado correctamente');
await this.cargarDocumentosPagos();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el documento');
}
} catch (e) {
console.error('Error eliminando documento:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el documento');
}
},

/* ================= COMENTARIOS ================= */

async abrirModalComentarios(id) {
this.solicitudComentariosId = id;
this.solicitudComentariosFolio = 'Solicitud #00' + id;
this.nuevoComentario = '';
this.comentarios = [];
this.errorComentario = false;

const offcanvasEl = document.getElementById('modalComentarios');
bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();

await this.cargarComentarios();
},

async cargarComentarios() {
this.cargandoComentarios = true;
try {
const resp = await axios.get(this.BASE + '/comentarios?id=' + this.solicitudComentariosId);
const json = resp.data;
this.comentarios = (json.success && json.data) ? json.data : [];
this.scrollChatToBottom();
} catch (e) {
console.error('Error cargando comentarios:', e);
this.comentarios = [];
} finally {
this.cargandoComentarios = false;
}
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async agregarComentario() {
if (this.guardandoComentario) return;

if (!this.nuevoComentario.trim()) {
this.errorComentario = true;
if (window.Notify) Notify.error('Ingresa un comentario válido.');
return;
}

this.guardandoComentario = true;
try {
const resp = await axios.post(this.BASE + '/comentario-store', {
id: this.solicitudComentariosId,
comentario: this.nuevoComentario.trim()
});
const json = resp.data;
if (json.success) {
this.nuevoComentario = '';
this.errorComentario = false;
await this.cargarComentarios();
if (window.Notify) Notify.success(json.message || 'Comentario agregado correctamente');
if (window.tablaPedidos) window.tablaPedidos.ajax.reload(null, false);
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el comentario');
}
} catch (e) {
console.error('Error agregando comentario:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al agregar el comentario');
} finally {
this.guardandoComentario = false;
}
},

downloadUrl(file) {
return '/download?tipo=pedido-aditivo&file=' + encodeURIComponent(file);
},

viewUrl(file) {
return this.downloadUrl(file) + '&view=1';
},

async eliminarSolicitud(id, nombre) {
const res = await this._eliminar(this.BASE + '/eliminar', { id: id }, nombre);
if (res && res.success) {
await this.actualizarPendientes();
}
},

/* ================= UTILIDADES DE PRESENTACIÓN ================= */

statusBadgeClass(status) {
if (status === 0) return 'bg-danger';
if (status === 1) return 'bg-warning-subtle text-warning-emphasis';
return 'bg-success-subtle text-success-emphasis';
},

}));

});