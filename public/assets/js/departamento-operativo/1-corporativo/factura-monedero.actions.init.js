function fmActualizarToolOpciones() {
var anchor = document.getElementById('fm-tools-anchor');
var wrapper = document.getElementById('fm-tools-wrapper');
var container = document.getElementById('container');
var mostrar = false;
var sel = document.getElementById('module-station-selector-factura-monedero');
if (sel) {
mostrar = sel.value.indexOf('estacion_') === 0 || sel.value.indexOf('depto_') === 0;
} else if (container && container.dataset.multiestacion !== 'true') {
mostrar = parseInt(container.dataset.idEstacion || '0') > 0;
}

if (mostrar) {
if (!wrapper) {
var tmpl = document.getElementById('fm-tools-tmpl');
if (tmpl && anchor) {
anchor.appendChild(tmpl.content.cloneNode(true));
wrapper = document.getElementById('fm-tools-wrapper');
}
}
if (wrapper) wrapper.style.display = '';
} else if (wrapper) {
wrapper.style.display = 'none';
}
actualizarBadgePendientes();
}

function actualizarBadgePendientes() {
var wrapper = document.getElementById('fm-pending-wrapper');
var countEl = document.getElementById('fm-pending-count');
if (!wrapper || !countEl) return;

var sel = document.getElementById('module-station-selector-factura-monedero');
if (sel) {
var opt = sel.options[sel.selectedIndex];
if (opt) {
var match = opt.textContent.match(/\((\d+)\)/);
if (match) {
countEl.textContent = parseInt(match[1]);
wrapper.classList.remove('d-none');
return;
}
}
}

var dataEl = document.getElementById('fm-pendientes-data');
if (!dataEl) return;
var raw = dataEl.textContent;
if (!raw || raw === '{}') return;
var pendientesMap;
try { pendientesMap = JSON.parse(raw); } catch(e) { return; }

var c = document.getElementById('container');
if (!c) return;
var esMultiestacion = c.dataset.multiestacion === 'true';
var key, est;
if (esMultiestacion) {
est = sessionStorage.getItem('fm_estacion') || '';
} else {
est = c.dataset.idEstacion || '';
}
if (est) key = 'estacion_' + est;
else key = 'total';

var count = pendientesMap[key];
countEl.textContent = (count === undefined) ? 0 : count;
wrapper.classList.remove('d-none');
}

document.addEventListener('fm-tabla-recargada', function() {
fmActualizarToolOpciones();
actualizarBadgePendientes();
});

document.addEventListener('DOMContentLoaded', function () {
var selFm = document.getElementById('module-station-selector-factura-monedero');
if (selFm) {
selFm.addEventListener('change', fmActualizarToolOpciones);
}
fmActualizarToolOpciones();
actualizarBadgePendientes();
});

window.addEventListener('load', function () {
fmActualizarToolOpciones();
});

document.addEventListener('alpine:init', () => {

Alpine.data('facturaMonederoComponent', () => ({

loading: false,
guardando: false,
editando: false,
editandoId: 0,
comentarioSolicitudId: null,
comentarios: [],
nuevoComentario: '',
guardandoComentario: false,
detalle: {},

form: {
no_factura: '',
monto: '',
folio: '',
fecha_creacion: '',
archivo_factura: '',
archivo_comprobante_pago: '',
archivo_factura_xml: '',
},
errors: {},

init() {
document.addEventListener('fm:ver-detalle', (e) => { this.abrirDetalle(e.detail.id); });
document.addEventListener('fm:ver-comentarios', (e) => { this.abrirComentarios(e.detail.id); });
document.addEventListener('fm:editar', (e) => { this.abrirModalEditar(e.detail.id); });
document.addEventListener('fm:eliminar', (e) => { this.confirmarEliminar(e.detail.id, e.detail.nombre); });
this.refrescarPendientes();
},

obtenerEstacionActual() {
const container = document.getElementById('container');
if (!container) return 0;
const multiestacion = container.dataset.multiestacion === 'true';
if (multiestacion) {
const est = sessionStorage.getItem('fm_estacion');
if (est && est !== '0') return parseInt(est);
return 0;
}
return parseInt(container.dataset.idEstacion || '0');
},

async refrescarPendientes() {
const container = document.getElementById('container');
if (!container) return;
const idYear = parseInt(container.dataset.idYear);
const idMes = parseInt(container.dataset.idMes);
try {
const resp = await fetch('/departamento-operativo/corporativo/factura-monedero/get-pendientes?year=' + idYear + '&mes=' + idMes);
const json = await resp.json();
if (!json.success || !json.data) return;
const pend = json.data;
const span = document.getElementById('fm-pendientes-data');
if (span) span.textContent = JSON.stringify(pend);
actualizarBadgePendientes();
const sel = document.getElementById('module-station-selector-factura-monedero');
if (!sel) return;
for (var i = 0; i < sel.options.length; i++) {
var opt = sel.options[i];
if (!opt.value) {
var total = pend.total || 0;
var placeholder = sel.dataset.placeholder || 'Todas las estaciones';
opt.textContent = placeholder + ' (' + total + ')';
} else if (opt.value.indexOf('estacion_') === 0) {
var id = parseInt(opt.value.split('_')[1]);
var count = pend['estacion_' + id] || 0;
var name = opt.textContent.replace(/\s*\(\d+\)$/, '');
opt.textContent = name + ' (' + count + ')';
}
}
if (typeof ModuleStationSelector !== 'undefined' && ModuleStationSelector._instances) {
var ms = ModuleStationSelector._instances['factura-monedero'];
if (ms && ms.updateBadge) ms.updateBadge();
}
} catch (e) {
console.error('Error al refrescar pendientes:', e);
}
},

showAlert(icon, title, text) {
Swal.fire({ icon, title, text, timer: 2000, showConfirmButton: false });
},

notify(type, message) {
if (window.Notify) Notify[type](message);
},

resetForm() {
this.editando = false;
this.editandoId = 0;
this.form = {
no_factura: '',
monto: '',
folio: '',
fecha_creacion: '',
archivo_factura: '',
archivo_comprobante_pago: '',
archivo_factura_xml: '',
};
this.errors = {};
if (this.$refs.fileFactura) this.$refs.fileFactura.value = '';
if (this.$refs.fileComprobante) this.$refs.fileComprobante.value = '';
if (this.$refs.fileXml) this.$refs.fileXml.value = '';
},

abrirModalNuevo() {
this.resetForm();
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaMonedero')).show();
},

async abrirModalEditar(id) {
this.resetForm();
this.editando = true;
this.editandoId = id;
try {
const res = await axios.get('/departamento-operativo/corporativo/factura-monedero/get-detalle/' + id);
if (res.data.success) {
const d = res.data.data;
this.form.no_factura = d.no_factura || '';
this.form.monto = d.monto ?? '';
this.form.folio = d.folio || 0;
this.form.fecha_creacion = d.fecha_creacion || '';
this.form.archivo_factura = d.archivo_factura || '';
this.form.archivo_comprobante_pago = d.archivo_comprobante_pago || '';
this.form.archivo_factura_xml = d.archivo_factura_xml || '';
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaMonedero')).show();
} else {
this.notify('error', 'Error al cargar el registro');
}
} catch (e) {
this.notify('error', 'Error al cargar el registro');
}
},

validar() {
this.errors = {};
const campos = [
{ key: 'no_factura', label: 'No. Factura', check: () => !this.form.no_factura },
{ key: 'monto', label: 'Monto', check: () => !this.form.monto || parseFloat(this.form.monto) <= 0 },
];
for (const c of campos) {
this.errors[c.key] = false;
if (c.check()) {
this.errors[c.key] = true;
this.notify('error', '* ' + c.label + ' requerido');
return false;
}
}
if (!this.editando) {
const factura = this.$refs.fileFactura ? this.$refs.fileFactura.files[0] : null;
if (!factura) {
this.notify('error', '* Factura (PDF) requerido');
return false;
}
const xml = this.$refs.fileXml ? this.$refs.fileXml.files[0] : null;
if (!xml) {
this.notify('error', '* Factura (XML) requerido');
return false;
}
}
return true;
},

async guardar() {
if (!this.validar()) return;
this.guardando = true;

try {
if (this.editando) {
const data = new FormData();
data.append('id', this.editandoId);
data.append('no_factura', this.form.no_factura);
data.append('monto', this.form.monto);
var facturaFile = this.$refs.fileFactura ? this.$refs.fileFactura.files[0] : null;
if (facturaFile) data.append('archivo_factura', facturaFile, facturaFile.name);
var comprobanteFile = this.$refs.fileComprobante ? this.$refs.fileComprobante.files[0] : null;
if (comprobanteFile) data.append('archivo_comprobante_pago', comprobanteFile, comprobanteFile.name);
var xmlFile = this.$refs.fileXml ? this.$refs.fileXml.files[0] : null;
if (xmlFile) data.append('archivo_factura_xml', xmlFile, xmlFile.name);
const response = await axios({
method: 'POST',
url: '/departamento-operativo/corporativo/factura-monedero/edit',
data: data,
});
const res = response.data;
if (res.success) {
this.showAlert('success', 'Correcto', 'Registro actualizado correctamente');
if (window.tablaFacturaMonedero) {
window.tablaFacturaMonedero.ajax.reload(null, false);
}
bootstrap.Modal.getInstance(document.getElementById('modalFacturaMonedero'))?.hide();
await this.refrescarPendientes();
} else {
this.notify('error', res.message || 'Error');
}
} else {
const data = new FormData();
data.append('year', parseInt(document.getElementById('container').dataset.idYear));
data.append('mes', parseInt(document.getElementById('container').dataset.idMes));
const idEstacion = this.obtenerEstacionActual();
if (idEstacion) data.append('id_estacion', idEstacion);
data.append('no_factura', this.form.no_factura);
data.append('monto', this.form.monto);
const factura = this.$refs.fileFactura.files[0];
const xml = this.$refs.fileXml.files[0];
if (factura) data.append('archivo_factura', factura, factura.name);
if (xml) data.append('archivo_factura_xml', xml, xml.name);
const response = await axios({
method: 'POST',
url: '/departamento-operativo/corporativo/factura-monedero/add',
data: data,
headers: { 'Content-Type': 'multipart/form-data' },
});
const res = response.data;
if (res.success) {
this.showAlert('success', 'Correcto', 'Registro creado correctamente');
if (window.tablaFacturaMonedero) {
window.tablaFacturaMonedero.ajax.reload(null, false);
}
bootstrap.Modal.getInstance(document.getElementById('modalFacturaMonedero'))?.hide();
await this.refrescarPendientes();
} else {
this.notify('error', res.message || 'Error');
}
}
} catch (err) {
const mensaje = err.response?.data?.message || err.message || 'Error en la solicitud';
this.notify('error', mensaje);
} finally {
this.guardando = false;
}
},

confirmarEliminar(id, name) {
this.deleteAction({
url: '/departamento-operativo/corporativo/factura-monedero/delete',
id: id,
name: name,
table: '#tabla-factura-monedero'
});
},

abrirDetalle(id) {
const row = window.tablaFacturaMonedero ? window.tablaFacturaMonedero.row(function (idx, data) {
return data.id === id;
}).data() : null;

if (row) {
this.detalle = row;
} else {
this.detalle = {
folio: 0, estacion_nombre: '', fecha_creacion: '', fecha_creacion_format: '', no_factura: '',
monto: 0, archivo_factura: '', archivo_comprobante_pago: '',
archivo_factura_xml: '', estado: 1
};
}
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();
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
const resp = await fetch('/departamento-operativo/corporativo/factura-monedero/get-comentarios?id=' + id);
const json = await resp.json();
if (json.success) {
this.comentarios = (json.data || []).map(c => ({
...c,
usuario_nombre: c.usuario_nombre || 'Sistema',
fecha_hora: c.fecha_hora || ''
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
const facturaId = this.comentarioSolicitudId;

try {
const resp = await fetch('/departamento-operativo/corporativo/factura-monedero/add-comentario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: facturaId, comentario: this.nuevoComentario })
});
const json = await resp.json();

if (json.success) {
this.nuevoComentario = '';
const resp2 = await fetch('/departamento-operativo/corporativo/factura-monedero/get-comentarios?id=' + facturaId);
const json2 = await resp2.json();
if (json2.success) {
this.comentarios = (json2.data || []).map(c => ({
...c,
usuario_nombre: c.usuario_nombre || 'Sistema',
fecha_hora: c.fecha_hora || ''
}));
this.scrollChatToBottom();
}
const dt = window.tablaFacturaMonedero;
if (dt) {
dt.rows().every(function () {
const d = this.data();
if (d.id === facturaId) {
d.total_comentarios = (d.total_comentarios || 0) + 1;
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

downloadFile(tipo, archivo) {
if (!archivo) { this.notify('error', 'Archivo no disponible'); return; }
window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(archivo), '_blank');
},

descargarExcel() {
var container = document.getElementById('container');
if (!container) { return; }
var idYear = container.dataset.idYear;
var idMes = container.dataset.idMes;
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) { return; }
window.location.href = '/departamento-operativo/corporativo/factura-monedero-excel/' + idYear + '/' + idMes + '/' + idEstacion;
},

descargarPdf() {
var container = document.getElementById('container');
if (!container) { return; }
var idYear = container.dataset.idYear;
var idMes = container.dataset.idMes;
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) { return; }
window.location.href = '/departamento-operativo/corporativo/factura-monedero-pdf/' + idYear + '/' + idMes + '/' + idEstacion;
},
}));
});
