function cargarFacturaStatusGlobal() {
const c = document.getElementById('container');
if (!c) return;
const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
var esMultiestacion = c.dataset.multiestacion === 'true';
var esGestoria = c.dataset.esGestoria === 'true';
var scId, scDepu;
if (esGestoria) {
scId = '8';
scDepu = '5';
} else if (esMultiestacion) {
scId = sessionStorage.getItem('sc_estacion') || '';
scDepu = sessionStorage.getItem('sc_depto') || '';
if (!scId && !scDepu) {
scId = c.dataset.idEstacion || '';
scDepu = c.dataset.idPuesto || '';
}
} else {
scId = c.dataset.idEstacion || '';
scDepu = c.dataset.idPuesto || '';
}
var esTodas = !scId && !scDepu && esMultiestacion;

var el = document.getElementById('factura-status-breadcrumb-badge');
if (!el) return;

if (esTodas) {
el.innerHTML = '';
return;
}

var stId = scId || '0';
var dpId = scDepu || '0';
if (stId === '0' && dpId > 0) stId = '8';
let url = '/departamento-operativo/solicitud-cheque/factura-status/' + idYear + '/' + idMes + '/' + stId + '/' + dpId;

fetch(url)
.then(r => r.json())
.then(json => {
if (json.success && json.data) {
var total = json.data.total || 0;
var pendientes = json.data.pendientes || 0;
var conPago = json.data.con_pago || 0;
var bg, text;
if (total === 0) {
bg = 'bg-danger';
text = 'Sin factura';
} else if (conPago > 0 && pendientes === 0) {
bg = 'bg-success';
text = 'Pagado';
} else {
bg = 'bg-warning text-white';
text = 'Factura disponible';
}
el.innerHTML = '<span class="badge rounded-pill ' + bg + ' ms-2">' + text + '</span>';
}
})
.catch(e => console.error('Error loading factura status:', e));
}

document.addEventListener('alpine:init', () => {

Alpine.data('solicitudChequeComponent', () => ({
detalle: null,
comentarios: [],
comentarioSolicitudId: null,
nuevoComentario: '',
guardandoComentario: false,
puedeAgregarComentarios: false,
idUsuario: 0,

archivoSolicitudId: null,
documentos: [],
nuevoDocumento: { tipo: '' },
subiendoDocumento: false,
puedeAgregarDocumentos: false,

pagoSolicitudId: null,
pagos: [],
subiendoPago: false,
puedeGestionarPagos: false,

telcelSolicitudId: null,
telcel: [],
nuevoTelcel: {},
guardandoTelcel: false,
puedeGestionarTelcel: false,

multiestacion: false,
telcelGlobal: [],
guardandoTelcelGlobal: false,
telcelGlobalYear: 0,
telcelGlobalMes: 0,
telcelGlobalEstacion: 0,
telcelEditandoId: null,
telcelEditando: null,

documentTypes: [
'PRESUPUESTO', 'FACTURA PDF', 'FACTURA XML', 'CARATULA BANCARIA',
'CONSTANCIA DE SITUACION', 'PREFACTURA', 'ORDEN DE SERVICIO', 'ORDEN DE COMPRA',
'ORDEN DE MANTENIMIENTO', 'PÓLIZA DE GARANTÍA', 'PRORRATEO', 'REEMBOLSO CAJA CHICA',
'COTIZACIÓN', 'NOTA DE CREDITO PDF', 'NOTA DE CREDITO XML', 'CONTRATO',
'COMPLEMENTO DE PAGO PDF', 'COMPLEMENTO DE PAGO XML', 'OPINIÓN DE CUMPLIMIENTO'
],

formatearFecha(fecha) {
if (!fecha) return '';
var d = new Date(fecha);
var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
return d.getDate() + ' de ' + meses[d.getMonth()] + ' del ' + d.getFullYear();
},

formatNum(v) {
if (v == null || isNaN(v)) return '';
return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

init() {
const c = document.getElementById('container');
if (!c) return;

this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
this.puedeAgregarComentarios = c.dataset.puedeAgregarComentarios === 'true';
this.puedeAgregarDocumentos = c.dataset.puedeAgregarDocumentos === 'true';
this.puedeGestionarPagos = c.dataset.puedeGestionarPagos === 'true';
this.puedeGestionarTelcel = c.dataset.puedeGestionarTelcel === 'true';

window.addEventListener('load', () => {
this.cargarFacturaStatus();

var est = sessionStorage.getItem('sc_estacion') || '';
var dep = sessionStorage.getItem('sc_depto') || '';
var select = document.getElementById('sc-selector-estacion');
if (select) {
if (dep) select.value = 'depto_' + dep;
else if (est) select.value = 'estacion_' + est;
else select.value = 'all';
}
if (typeof actualizarBadgeTexto === 'function') {
actualizarBadgeTexto();
}
if (typeof actualizarToolOpciones === 'function') {
actualizarToolOpciones();
}

document.addEventListener('solicitud-guardada', () => {
location.reload();
});
document.addEventListener('solicitud-eliminada', () => {
location.reload();
});
});

this.bindModalSelect2({
modalRef: 'modalArchivos',
selectRef: 'tipoDocumentoSelect',
wrapperRef: 'tipoDocumentoWrapper',
model: 'nuevoDocumento.tipo',
options: { placeholder: 'Selecciona...', allowClear: true }
});

document.addEventListener('abrir-comentarios', (e) => { this.abrirComentarios(e.detail.id); });
document.addEventListener('ver-detalle', (e) => { this.abrirDetalle(e.detail.id); });
document.addEventListener('eliminar-solicitud', (e) => { this.confirmarEliminar(e.detail.id); });
document.addEventListener('ver-documentos', (e) => { this.abrirModalArchivos(e.detail.id); });
document.addEventListener('ver-pagos', (e) => { this.abrirModalPagos(e.detail.id); });
document.addEventListener('ver-telcel', (e) => { this.abrirModalTelcel(e.detail.id); });
document.addEventListener('ver-pdf', (e) => {
window.location.href = '/departamento-operativo/solicitud-cheque-pdf/' + e.detail.id;
});
document.addEventListener('ver-telcel-global', (e) => {
this.abrirModalTelcelGlobal(e.detail.estacion, e.detail.year, e.detail.mes);
});
document.addEventListener('abrir-global-telcel', () => {
var c = document.getElementById('container');
if (!c) return;
var year = c.dataset.idYear;
var mes = c.dataset.idMes;
var esMultiestacion = c.dataset.multiestacion === 'true';
var esGestoria = c.dataset.esGestoria === 'true';
var est;
if (esGestoria) {
est = '8';
} else if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
if (!est) est = c.dataset.idEstacion || '';
} else {
est = c.dataset.idEstacion || '';
}
if (est) this.abrirModalTelcelGlobal(est, year, mes);
});
},

cargarFacturaStatus() {
cargarFacturaStatusGlobal();
},

async abrirDetalle(id) {
const resp = await fetch('/departamento-operativo/solicitud-cheque/detalle?id=' + id);
const json = await resp.json();
if (json.success && json.data) {
this.detalle = json.data;
new bootstrap.Modal(document.getElementById('modalDetalle')).show();
} else {
if (window.Notify) Notify.error('Error al cargar detalle');
}
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id) {
this.comentarioSolicitudId = id;
this.nuevoComentario = '';
this.comentarios = [];

try {
const resp = await fetch('/departamento-operativo/solicitud-cheque/comentarios?id=' + id);
const json = await resp.json();
if (json.success) {
this.comentarios = (json.comentarios || []).map(c => ({
...c,
esMio: c.id_usuario === this.idUsuario,
usuario_nombre: c.usuario?.nombre || 'Sistema',
fecha_formateada: this.formatearFecha(c.fecha_hora)
}));
this.scrollChatToBottom();
}
} catch (e) {
console.error('Error cargando comentarios:', e);
}

bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('modalComentarios')).show();
},

async agregarComentario() {
if (this.guardandoComentario) return;
if (!this.nuevoComentario.trim()) return;
if (!this.comentarioSolicitudId) return;

this.guardandoComentario = true;

try {
const fd = new FormData();
fd.append('id_solicitud', this.comentarioSolicitudId);
fd.append('comentario', this.nuevoComentario);

const resp = await fetch('/departamento-operativo/solicitud-cheque/store-comentario', {
method: 'POST',
body: fd
});
const json = await resp.json();

if (json.success) {
this.nuevoComentario = '';
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/comentarios?id=' + this.comentarioSolicitudId);
const json2 = await resp2.json();
if (json2.success) {
this.comentarios = (json2.comentarios || []).map(c => ({
...c,
esMio: c.id_usuario === this.idUsuario,
usuario_nombre: c.usuario?.nombre || 'Sistema',
fecha_formateada: this.formatearFecha(c.fecha_hora)
}));
this.scrollChatToBottom();
}
var solicitudId = this.comentarioSolicitudId;
const dt = $('#tabla-solicitud-cheque').DataTable();
if (dt) {
dt.rows().every(function () {
const d = this.data();
if (d.id === solicitudId) {
d.num_comentarios = (d.num_comentarios || 0) + 1;
this.invalidate();
dt.draw(false);
return false;
}
});
}
if (window.Notify) Notify.success('Comentario agregado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar comentario');
}
} catch (e) {
console.error('Error al agregar comentario:', e);
if (window.Notify) Notify.error('Error al agregar comentario');
} finally {
this.guardandoComentario = false;
}
},

async confirmarEliminar(id) {
await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete',
id: id,
name: 'Solicitud #' + id,
table: '#tabla-solicitud-cheque'
});
},

async abrirModalArchivos(id) {
this.archivoSolicitudId = id;
this.nuevoDocumento = { tipo: '' };
this.documentos = [];
const resp = await fetch('/departamento-operativo/solicitud-cheque/documentos?id=' + id);
const json = await resp.json();
if (json.success) this.documentos = json.documentos || [];
new bootstrap.Modal(document.getElementById('modalArchivos')).show();
},

async subirDocumento() {
const $select = $(this.$refs.tipoDocumentoSelect);
const tipo = $select.val() || '';
if (!tipo) { if (window.Notify) Notify.error('Selecciona un tipo de documento'); return; }
const fileInput = this.$refs.nuevoDocumentoFile;
if (!fileInput || !fileInput.files || !fileInput.files[0]) { if (window.Notify) Notify.error('Selecciona un archivo'); return; }

this.subiendoDocumento = true;
try {
const fd = new FormData();
fd.append('id_solicitud', this.archivoSolicitudId);
fd.append('descripcion', tipo);
fd.append('archivo', fileInput.files[0]);

const resp = await fetch('/departamento-operativo/solicitud-cheque/store-documento', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
fileInput.value = '';
$select.val('').trigger('change.select2');
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/documentos?id=' + this.archivoSolicitudId);
const json2 = await resp2.json();
if (json2.success) this.documentos = json2.documentos || [];
if (window.Notify) Notify.success('Documento agregado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al subir');
}
} catch (e) {
console.error('Error al subir documento:', e);
if (window.Notify) Notify.error('Error al subir documento');
} finally {
this.subiendoDocumento = false;
}
},

async eliminarDocumento(id) {
const res = await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete-documento',
id: id,
name: 'Documento #' + id
});
if (res?.success) {
const resp = await fetch('/departamento-operativo/solicitud-cheque/documentos?id=' + this.archivoSolicitudId);
const json = await resp.json();
if (json.success) this.documentos = json.documentos || [];
}
},

async abrirModalPagos(id) {
this.pagoSolicitudId = id;
this.pagos = [];
const resp = await fetch('/departamento-operativo/solicitud-cheque/pagos?id=' + id);
const json = await resp.json();
if (json.success) this.pagos = json.pagos || [];
new bootstrap.Modal(document.getElementById('modalPagos')).show();
},

async subirPago() {
const fileInput = this.$refs.nuevoPagoFile;
if (!fileInput || !fileInput.files || !fileInput.files[0]) { if (window.Notify) Notify.error('Selecciona un archivo'); return; }

this.subiendoPago = true;
try {
const fd = new FormData();
fd.append('id_solicitud', this.pagoSolicitudId);
fd.append('archivo', fileInput.files[0]);

const resp = await fetch('/departamento-operativo/solicitud-cheque/store-pago', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
fileInput.value = '';
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/pagos?id=' + this.pagoSolicitudId);
const json2 = await resp2.json();
if (json2.success) this.pagos = json2.pagos || [];
if (window.Notify) Notify.success('Pago registrado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al subir');
}
} catch (e) {
console.error('Error al subir pago:', e);
} finally {
this.subiendoPago = false;
}
},

async eliminarPago(id) {
const res = await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete-pago',
id: id,
name: 'Comprobante #' + id
});
if (res?.success) {
const resp = await fetch('/departamento-operativo/solicitud-cheque/pagos?id=' + this.pagoSolicitudId);
const json = await resp.json();
if (json.success) this.pagos = json.pagos || [];
}
},

async abrirModalTelcel(id) {
this.telcelSolicitudId = id;
this.nuevoTelcel = {};
this.telcel = [];
const resp = await fetch('/departamento-operativo/solicitud-cheque/telcel?id=' + id);
const json = await resp.json();
if (json.success) this.telcel = json.telcel || [];
new bootstrap.Modal(document.getElementById('modalTelcel')).show();
},

async agregarTelcel() {
if (!this.$refs.nuevoTelcelFile || !this.$refs.nuevoTelcelFile.files || !this.$refs.nuevoTelcelFile.files[0]) {
if (window.Notify) Notify.error('Seleccione un archivo PDF de factura');
return;
}

this.guardandoTelcel = true;
try {
const fd = new FormData();
fd.append('id_solicitud', this.telcelSolicitudId);
fd.append('factura', this.$refs.nuevoTelcelFile.files[0]);

const resp = await fetch('/departamento-operativo/solicitud-cheque/store-telcel', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
this.$refs.nuevoTelcelFile.value = '';
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/telcel?id=' + this.telcelSolicitudId);
const json2 = await resp2.json();
if (json2.success) this.telcel = json2.telcel || [];
if (window.Notify) Notify.success('Factura Telcel agregada');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar');
}
} catch (e) {
console.error('Error al agregar telcel:', e);
} finally {
this.guardandoTelcel = false;
}
},

getFirma(tipo) {
if (!this.detalle || !this.detalle.firmas) return null;
return this.detalle.firmas.find(f => f.tipo_firma === tipo) || null;
},

download(tipo, file) {
if (!file) return;
window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(file), '_blank');
},

async eliminarTelcel(id) {
const res = await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete-telcel',
id: id,
name: 'Factura Telcel #' + id
});
if (res?.success) {
const resp = await fetch('/departamento-operativo/solicitud-cheque/telcel?id=' + this.telcelSolicitudId);
const json = await resp.json();
if (json.success) this.telcel = json.telcel || [];
}
},

async abrirModalTelcelGlobal(estacion, year, mes) {
var c = document.getElementById('container');
this.multiestacion = c ? c.dataset.multiestacion === 'true' : false;
this.telcelGlobalEstacion = estacion;
this.telcelGlobalYear = year;
this.telcelGlobalMes = mes;
this.telcelGlobal = [];
this.telcelEditandoId = null;
this.telcelEditando = null;
const resp = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + year + '&idMes=' + mes + '&idEstacion=' + estacion);
const json = await resp.json();
if (json.success) this.telcelGlobal = json.telcel || [];
new bootstrap.Modal(document.getElementById('modalTelcelGlobal')).show();
},

async agregarTelcelGlobal() {
var fileInput = this.$refs.nuevoTelcelGlobalFile;
if (!fileInput || !fileInput.files || !fileInput.files[0]) {
if (window.Notify) Notify.error('Seleccione un archivo PDF de factura');
return;
}
this.guardandoTelcelGlobal = true;
try {
const fd = new FormData();
fd.append('idYear', this.telcelGlobalYear);
fd.append('idMes', this.telcelGlobalMes);
fd.append('idEstacion', this.telcelGlobalEstacion);
fd.append('factura', fileInput.files[0]);
const resp = await fetch('/departamento-operativo/solicitud-cheque/store-telcel-global', { method: 'POST', body: fd });
const json = await resp.json();
if (json.success) {
fileInput.value = '';
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + this.telcelGlobalYear + '&idMes=' + this.telcelGlobalMes + '&idEstacion=' + this.telcelGlobalEstacion);
const json2 = await resp2.json();
if (json2.success) this.telcelGlobal = json2.telcel || [];
if (window.Notify) Notify.success('Factura Telcel agregada');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar');
}
} catch (e) {
console.error('Error al agregar telcel global:', e);
} finally {
this.guardandoTelcelGlobal = false;
}
},

async eliminarTelcelGlobal(id) {
const res = await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete-telcel',
id: id,
name: 'Factura Telcel #' + id
});
if (res?.success) {
const resp = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + this.telcelGlobalYear + '&idMes=' + this.telcelGlobalMes + '&idEstacion=' + this.telcelGlobalEstacion);
const json = await resp.json();
if (json.success) this.telcelGlobal = json.telcel || [];
}
},

editarTelcelGlobal(t) {
this.telcelEditandoId = t.id;
this.telcelEditando = t;
},

cancelarEditarTelcelGlobal() {
this.telcelEditandoId = null;
this.telcelEditando = null;
},

async guardarEditarTelcelGlobal() {
this.guardandoTelcelGlobal = true;
try {
const fd = new FormData();
fd.append('id', this.telcelEditandoId);
if (this.multiestacion) {
var fFile = this.$refs.editarFacturaFile;
if (fFile && fFile.files && fFile.files[0]) fd.append('factura', fFile.files[0]);
}
var pFile = this.$refs.editarPagoFile;
if (pFile && pFile.files && pFile.files[0]) fd.append('c_pago', pFile.files[0]);
const resp = await fetch('/departamento-operativo/solicitud-cheque/update-pago-telcel', { method: 'POST', body: fd });
const json = await resp.json();
if (json.success) {
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + this.telcelGlobalYear + '&idMes=' + this.telcelGlobalMes + '&idEstacion=' + this.telcelGlobalEstacion);
const json2 = await resp2.json();
if (json2.success) this.telcelGlobal = json2.telcel || [];
this.telcelEditandoId = null;
this.telcelEditando = null;
if (window.Notify) Notify.success('Registro actualizado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al actualizar');
}
} catch (e) {
console.error('Error al editar:', e);
} finally {
this.guardandoTelcelGlobal = false;
}
},

uploadComprobanteGlobal(t) {
var input = document.createElement('input');
input.type = 'file';
input.accept = '.pdf,.jpg,.png';
var self = this;
input.onchange = function(e) {
self.subirComprobanteGlobal(t, e);
};
input.click();
},

async subirComprobanteGlobal(t, event) {
var file = event.target.files[0];
if (!file) return;
try {
const fd = new FormData();
fd.append('id', t.id);
fd.append('c_pago', file);
const resp = await fetch('/departamento-operativo/solicitud-cheque/update-pago-telcel', { method: 'POST', body: fd });
const json = await resp.json();
if (json.success) {
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + this.telcelGlobalYear + '&idMes=' + this.telcelGlobalMes + '&idEstacion=' + this.telcelGlobalEstacion);
const json2 = await resp2.json();
if (json2.success) this.telcelGlobal = json2.telcel || [];
if (window.Notify) Notify.success('Comprobante subido');
} else {
if (window.Notify) Notify.error(json.message || 'Error al subir comprobante');
}
} catch (e) {
console.error('Error al subir comprobante:', e);
}
},

async eliminarComprobanteGlobal(t) {
const res = await this.deleteAction({
url: '/departamento-operativo/solicitud-cheque/delete-comprobante-telcel',
id: t.id,
name: 'Comprobante'
});
if (res?.success) {
const resp2 = await fetch('/departamento-operativo/solicitud-cheque/telcel-global?idYear=' + this.telcelGlobalYear + '&idMes=' + this.telcelGlobalMes + '&idEstacion=' + this.telcelGlobalEstacion);
const json2 = await resp2.json();
if (json2.success) this.telcelGlobal = json2.telcel || [];
}
},
}));

});

// Global functions for solicitud-cheque
function cambiarEstacion(sel) {
var val = sel.value;
if (val === 'all') {
sessionStorage.removeItem('sc_estacion');
sessionStorage.removeItem('sc_depto');
} else if (val.startsWith('depto_')) {
sessionStorage.setItem('sc_depto', val.replace('depto_', ''));
sessionStorage.removeItem('sc_estacion');
} else {
sessionStorage.setItem('sc_estacion', val.replace('estacion_', ''));
sessionStorage.removeItem('sc_depto');
}
actualizarBadgeTexto();
actualizarToolOpciones();
cargarFacturaStatusGlobal();
var dt = $('#tabla-solicitud-cheque').DataTable();
if (dt) dt.ajax.reload();
}

function actualizarBadgeTexto() {
var c = document.getElementById('container');
if (!c) return;
var esGestoria = c.dataset.esGestoria === 'true';
if (esGestoria) {
var badge = document.getElementById('sc-badge');
if (badge) badge.textContent = 'Gestoría';
return;
}
var esMultiestacion = c.dataset.multiestacion === 'true';
if (!esMultiestacion) return;
var est = sessionStorage.getItem('sc_estacion') || '';
var dep = sessionStorage.getItem('sc_depto') || '';
var texto = 'Todas las estaciones y departamentos';
if (dep) {
var opt = document.querySelector('#sc-selector-estacion option[value="depto_' + dep + '"]');
if (opt) texto = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
} else if (est) {
var opt = document.querySelector('#sc-selector-estacion option[value="estacion_' + est + '"]');
if (opt) texto = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
}
var badge = document.getElementById('sc-badge');
if (badge) badge.textContent = texto;
}

function actualizarBadgePendientes() {
var dataEl = document.getElementById('sc-pendientes-data');
if (!dataEl) return;
var raw = dataEl.textContent;
if (!raw || raw === '{}') return;
var pendientesMap;
try { pendientesMap = JSON.parse(raw); } catch(e) { return; }

var c = document.getElementById('container');
if (!c) return;
if (c.dataset.esGestoria === 'true') {
sessionStorage.setItem('sc_estacion', '8');
sessionStorage.setItem('sc_depto', '5');
}
var esMultiestacion = c.dataset.multiestacion === 'true';
var key, est, dep;
if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
dep = sessionStorage.getItem('sc_depto') || '';
} else {
est = c.dataset.idEstacion || '';
if (est === '8') {
dep = c.dataset.idPuesto || '';
}
}
if (dep) key = 'depto_' + dep;
else if (est) key = 'estacion_' + est;
else key = 'total';

var count = pendientesMap[key];
if (count === undefined) count = 0;
var wrapper = document.getElementById('sc-pending-wrapper');
var countEl = document.getElementById('sc-pending-count');
if (!wrapper || !countEl) return;
countEl.textContent = count;
wrapper.classList.remove('d-none');
}

document.addEventListener('tabla-recargada', function() {
actualizarBadgePendientes();
});

function actualizarToolOpciones() {
var c = document.getElementById('container');
if (!c) return;
if (c.dataset.esGestoria === 'true') {
sessionStorage.setItem('sc_estacion', '8');
sessionStorage.setItem('sc_depto', '5');
}
var esMultiestacion = c.dataset.multiestacion === 'true';
var est = sessionStorage.getItem('sc_estacion') || '';
var dep = sessionStorage.getItem('sc_depto') || '';
if (!est && !dep) {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
var esContextoDepartamento = parseInt(dep) > 0;

actualizarBadgePendientes();

var tools = document.getElementById('sc-tools-wrapper');
if (tools) tools.remove();

var anchor = document.getElementById('sc-tools-anchor');
var tmpl = document.getElementById('sc-tools-tmpl');
if (!anchor || !tmpl) return;
anchor.innerHTML = '';
anchor.appendChild(tmpl.content.cloneNode(true));
tools = document.getElementById('sc-tools-wrapper');
if (!tools) return;

// "Nueva solicitud" siempre visible
var agregar = document.getElementById('sc-tool-agregar');
if (agregar) agregar.style.display = '';

// Contexto de departamento → solo "Nueva solicitud"
// Contexto de estación multiestación → todas las opciones
// Contexto de estación (usuario de una estación) → solo si es contabilidad
var mostrarExtras = !esContextoDepartamento && (esMultiestacion || c.dataset.esContabilidad === 'true');

var telcel = document.getElementById('sc-tool-telcel');
if (telcel) telcel.style.display = mostrarExtras ? '' : 'none';

var comprobante = document.getElementById('sc-tool-comprobante');
if (comprobante) comprobante.style.display = mostrarExtras ? '' : 'none';

var excel = document.getElementById('sc-tool-excel');
if (excel) excel.style.display = mostrarExtras ? '' : 'none';

tools.style.display = '';
}

function irACrearSolicitud() {
var c = document.getElementById('container');
if (!c) return;
var year = c.dataset.idYear;
var mes = c.dataset.idMes;
var esMultiestacion = c.dataset.multiestacion === 'true';
var esGestoria = c.dataset.esGestoria === 'true';
var est, dep;
if (esGestoria) {
est = '8';
dep = '5';
} else if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
dep = sessionStorage.getItem('sc_depto') || '';
} else {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
var url = '/departamento-operativo/solicitud-cheque-crear/' + year + '/' + mes;
if (dep && dep !== '0') url += '/8/' + dep;
else if (est) url += '/' + est;
window.location.href = url;
}

function abrirFacturasTelcelGlobal() {
var c = document.getElementById('container');
if (!c) return;
var year = c.dataset.idYear;
var mes = c.dataset.idMes;
var esMultiestacion = c.dataset.multiestacion === 'true';
var esGestoria = c.dataset.esGestoria === 'true';
var est, dep;
if (esGestoria) {
est = '8';
dep = '5';
} else if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
dep = sessionStorage.getItem('sc_depto') || '';
if (!est) {
est = c.dataset.idEstacion || '';
}
} else {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
if (est) {
var url = '/departamento-operativo/solicitud-cheque-telcel/' + year + '/' + mes + '/' + est;
if (dep) url += '/' + dep;
window.location.href = url;
}
}

function abrirComprobantePago() {
document.dispatchEvent(new CustomEvent('abrir-global-telcel'));
}

function descargarExcel() {
var c = document.getElementById('container');
if (!c) return;
var year = c.dataset.idYear;
var mes = c.dataset.idMes;
var esMultiestacion = c.dataset.multiestacion === 'true';
var esGestoria = c.dataset.esGestoria === 'true';
var est, dep;
if (esGestoria) {
est = '8';
dep = '5';
} else if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
dep = sessionStorage.getItem('sc_depto') || '';
} else {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
var params = [];
if (est) params.push('estacion=' + est);
if (dep) params.push('depto=' + dep);
var qs = params.join('&');
window.location.href = '/departamento-operativo/solicitud-cheque/excel/' + year + '/' + mes + (qs ? '?' + qs : '');
}

document.addEventListener('DOMContentLoaded', function() {
actualizarToolOpciones();
var badge = document.querySelector('span.mb-1.badge.rounded-pill.text-bg-info');
if (badge && document.getElementById('sc-badge')) badge.style.display = 'none';
});
