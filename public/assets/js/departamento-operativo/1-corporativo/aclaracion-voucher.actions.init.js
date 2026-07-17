function avActualizarPendientes() {
var c = document.getElementById('container');
if (!c) return;
var idEstacion = 0;
var est = sessionStorage.getItem('av_estacion') || '';
if (est) { idEstacion = parseInt(est); }
else {
var sel = document.getElementById('module-station-selector-aclaracion-voucher');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) idEstacion = parseInt(p[1]);
}
if (!idEstacion && typeof ModuleStationSelector !== 'undefined') {
var ms = ModuleStationSelector._instances && ModuleStationSelector._instances['aclaracion-voucher'];
if (ms) {
var v = ms.getValue();
if (v.id_estacion) idEstacion = v.id_estacion;
}
}
}
if (!idEstacion) idEstacion = parseInt(c.dataset.idEstacion) || 0;
var dataEl = document.getElementById('av-pendientes-data');
if (!dataEl) return;
var raw = dataEl.textContent;
if (!raw || raw === '{}') return;
var pendientesMap;
try { pendientesMap = JSON.parse(raw); } catch (e) { return; }
var key = idEstacion > 0 ? 'estacion_' + idEstacion : 'total';
var count = pendientesMap[key];
if (count === undefined) count = 0;
var countEl = document.getElementById('av-pending-count');
if (countEl) countEl.textContent = count;
}

function avActualizarToolOpciones() {
var c = document.getElementById('container');
if (!c) return;
var esMultiestacion = c.dataset.multiestacion === 'true';
var idEstacion = 0;
var est = sessionStorage.getItem('av_estacion') || '';
if (est) { idEstacion = parseInt(est); }
else {
var sel = document.getElementById('module-station-selector-aclaracion-voucher');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) idEstacion = parseInt(p[1]);
}
if (!idEstacion && typeof ModuleStationSelector !== 'undefined') {
var ms = ModuleStationSelector._instances && ModuleStationSelector._instances['aclaracion-voucher'];
if (ms) {
var v = ms.getValue();
if (v.id_estacion) idEstacion = v.id_estacion;
}
}
}
if (!idEstacion) idEstacion = parseInt(c.dataset.idEstacion) || 0;
var esTodas = esMultiestacion && !idEstacion;
var tools = document.getElementById('av-tools-wrapper');
if (esTodas) {
if (tools) tools.remove();
return;
}
if (!tools) {
var tmpl = document.getElementById('av-tools-tmpl');
var anchor = document.getElementById('av-tools-anchor');
if (tmpl && anchor) {
anchor.innerHTML = '';
anchor.appendChild(tmpl.content.cloneNode(true));
}
}
avActualizarPendientes();
}

document.addEventListener('alpine:init', () => {

Alpine.data('aclaracionVoucherComponent', () => ({
detalle: { id: 0, solicitante_nombre: '', fecha_creacion: '', nombre_ticket: '', fecha: '', hora: '', valera: '', importe: 0, numero_aclaracion: '', doc_ticket: null, doc_voucher: null, pagado: 0, estado: 0 },
editando: false,
editandoId: 0,
puedeCrear: false,
puedeEditar: false,
esComercializadora: false,
multiestacion: false,

form: { nombre_ticket: '', fecha: '', hora: '', valera: '', importe: '', numero_aclaracion: '', pagado: '0', solicitante_nombre: '', fecha_creacion: '' },
errors: {},

comentarios: [],
nuevoComentario: '',
guardandoComentario: false,
comentarioSolicitudId: null,

anexos: [],
anexoForm: { descripcion: '' },
anexoSolicitudId: null,
subiendoAnexo: false,
guardando: false,

init() {
var c = document.getElementById('container');
if (!c) return;
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.esComercializadora = c.dataset.esComercializadora === 'true';
this.multiestacion = c.dataset.multiestacion === 'true';

document.addEventListener('av:ver-detalle', (e) => { this.abrirDetalle(e.detail.id); });
document.addEventListener('av:ver-comentarios', (e) => { this.abrirComentarios(e.detail.id); });
document.addEventListener('av:ver-anexos', (e) => { this.abrirAnexos(e.detail.id); });
document.addEventListener('av:editar', (e) => { this.abrirEditar(e.detail.id); });
document.addEventListener('av:eliminar', (e) => { this.confirmarEliminar(e.detail.id, e.detail.nombre_ticket); });
document.addEventListener('av-tabla-recargada', () => {
avActualizarPendientes();
avActualizarToolOpciones();
});

window.addEventListener('load', () => {
avActualizarPendientes();
avActualizarToolOpciones();
});
},

abrirModalAgregar() {
this.editando = false;
this.editandoId = 0;
this.errors = {};
this.form = { nombre_ticket: '', fecha: '', hora: '', valera: '', importe: '', numero_aclaracion: '', pagado: '0', solicitante_nombre: '', fecha_creacion: '' };
var ft = document.getElementById('fileTicket'); if (ft) ft.value = '';
var fv = document.getElementById('fileVoucher'); if (fv) fv.value = '';
new bootstrap.Modal('#modalAclaracion').show();
},

abrirEditar(id) {
var dt = $('#tabla-aclaracion-voucher').DataTable();
if (!dt) return;
var row = dt.rows().data().toArray().find(function (r) { return r.id === id; });
if (!row) return;
this.editando = true;
this.editandoId = id;
this.errors = {};
var ft = document.getElementById('fileTicket'); if (ft) ft.value = '';
var fv = document.getElementById('fileVoucher'); if (fv) fv.value = '';
this.form = {
nombre_ticket: row.nombre_ticket, fecha: row.fecha_raw || '', hora: row.hora_raw || '',
valera: row.valera, importe: row.importe, numero_aclaracion: row.numero_aclaracion,
pagado: String(row.pagado), solicitante_nombre: row.solicitante_nombre,
fecha_creacion: row.fecha_creacion
};
new bootstrap.Modal('#modalAclaracion').show();
},

guardar() {
if (this.guardando) return;
this.errors = {};
var valido = true;
if (!this.form.nombre_ticket) { this.errors.nombre_ticket = true; valido = false; }
if (!this.form.fecha) { this.errors.fecha = true; valido = false; }
if (!this.form.hora) { this.errors.hora = true; valido = false; }
if (!this.form.valera) { this.errors.valera = true; valido = false; }
if (!this.form.importe || parseFloat(this.form.importe) <= 0) { this.errors.importe = true; valido = false; }
if (!this.form.numero_aclaracion) { this.errors.numero_aclaracion = true; valido = false; }
if (!valido) return;

this.guardando = true;
var c = document.getElementById('container');
var fd = new FormData();

if (this.editando) {
fd.append('id', this.editandoId);
fd.append('pagado', this.form.pagado);
} else {
var idEstacion = 0;
if (this.multiestacion) {
if (typeof ModuleStationSelector !== 'undefined') {
var ms = ModuleStationSelector._instances && ModuleStationSelector._instances['aclaracion-voucher'];
if (ms) {
var sv = ms.getValue();
if (sv.id_estacion) idEstacion = sv.id_estacion;
}
}
if (!idEstacion) {
var est = sessionStorage.getItem('av_estacion') || '';
if (est) idEstacion = parseInt(est);
}
if (!idEstacion) {
var sel = document.getElementById('module-station-selector-aclaracion-voucher');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) idEstacion = parseInt(p[1]);
}
}
}
if (!idEstacion) idEstacion = parseInt(c.dataset.idEstacion) || 0;
if (!idEstacion) { if (window.Notify) Notify.error('Selecciona una estación antes de guardar.'); this.guardando = false; return; }
fd.append('id_estacion', idEstacion || '0');
fd.append('year', c.dataset.idYear);
fd.append('mes', c.dataset.idMes);
}

fd.append('nombre_ticket', this.form.nombre_ticket);
fd.append('fecha', this.form.fecha);
fd.append('hora', this.form.hora);
fd.append('valera', this.form.valera);
fd.append('importe', this.form.importe);
fd.append('numero_aclaracion', this.form.numero_aclaracion);

var ticketFile = document.getElementById('fileTicket');
var voucherFile = document.getElementById('fileVoucher');
if (ticketFile && ticketFile.files[0]) fd.append('ticket_file', ticketFile.files[0]);
if (voucherFile && voucherFile.files[0]) fd.append('voucher_file', voucherFile.files[0]);

var url = this.editando ? '/departamento-operativo/corporativo/aclaracion-voucher/edit' : '/departamento-operativo/corporativo/aclaracion-voucher/add';
var self = this;

fetch(url, { method: 'POST', body: fd })
.then(function (r) { return r.json(); })
.then(function (json) {
if (json.success) {
if (window.Notify) Notify.success(json.message);
bootstrap.Modal.getInstance('#modalAclaracion').hide();
var dt = $('#tabla-aclaracion-voucher').DataTable();
if (dt) dt.ajax.reload(null, false);
} else {
if (window.Notify) Notify.error(json.message || 'Error al guardar.');
}
})
.catch(function () { if (window.Notify) Notify.error('Error al guardar.'); })
.finally(function () { self.guardando = false; });
},

async confirmarEliminar(id, nombreTicket) {
await this.deleteAction({
url: '/departamento-operativo/corporativo/aclaracion-voucher/delete',
id: id,
name: nombreTicket || 'Aclaración #' + id,
table: '#tabla-aclaracion-voucher'
});
},

async finalizarSolicitud() {
var result = await Swal.fire({
title: '¿Estás seguro?',
text: '¿Deseas finalizar la solicitud de aclaración?',
icon: 'question', showCancelButton: true,
confirmButtonText: 'Sí, finalizar', cancelButtonText: 'Cancelar', reverseButtons: true
});
if (!result.isConfirmed) return;
var self = this;
fetch('/departamento-operativo/corporativo/aclaracion-voucher/finalizar', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: self.editandoId })
})
.then(function (r) { return r.json(); })
.then(function (json) {
if (window.Notify) Notify[json.success ? 'success' : 'error'](json.message);
if (json.success) {
bootstrap.Modal.getInstance('#modalAclaracion').hide();
var dt = $('#tabla-aclaracion-voucher').DataTable();
if (dt) dt.ajax.reload(null, false);
}
})
.catch(function () { if (window.Notify) Notify.error('Error al finalizar.'); });
},

abrirDetalle(id) {
var dt = $('#tabla-aclaracion-voucher').DataTable();
if (!dt) return;
var row = dt.rows().data().toArray().find(function (r) { return r.id === id; });
if (row) {
this.detalle = row;
this.detalle.importe = parseFloat(row.importe || 0).toFixed(2);
new bootstrap.Modal('#modalDetalle').show();
}
},

scrollChatToBottom() {
this.$nextTick(() => {
var el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id) {
this.comentarioSolicitudId = id;
this.nuevoComentario = '';
this.comentarios = [];
try {
var resp = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/get-comentarios?id=' + id);
var json = await resp.json();
if (json.success) {
this.comentarios = json.data || [];
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
var resp = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/add-comentario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: this.comentarioSolicitudId, comentario: this.nuevoComentario })
});
var json = await resp.json();
if (json.success) {
this.nuevoComentario = '';
var resp2 = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/get-comentarios?id=' + this.comentarioSolicitudId);
var json2 = await resp2.json();
if (json2.success) {
this.comentarios = json2.data || [];
this.scrollChatToBottom();
}
var solicitudId = this.comentarioSolicitudId;
var dt = $('#tabla-aclaracion-voucher').DataTable();
if (dt) {
dt.rows().every(function () {
var d = this.data();
if (d.id === solicitudId) {
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

async abrirAnexos(id) {
this.anexoSolicitudId = id;
this.anexoForm = { descripcion: '' };
var fa = document.getElementById('fileAnexo'); if (fa) fa.value = '';
this.anexos = [];
try {
var resp = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/get-anexos?id=' + id);
var json = await resp.json();
if (json.success) this.anexos = json.data || [];
} catch (e) { console.error('Error cargando anexos:', e); }
new bootstrap.Modal('#modalAnexos').show();
},

async agregarAnexo() {
if (this.subiendoAnexo) return;
if (!this.anexoForm.descripcion.trim()) { if (window.Notify) Notify.error('La descripción es requerida.'); return; }
var fileInput = document.getElementById('fileAnexo');
if (!fileInput || !fileInput.files[0]) { if (window.Notify) Notify.error('Debe seleccionar un archivo.'); return; }
this.subiendoAnexo = true;
var fd = new FormData();
fd.append('id', this.anexoSolicitudId);
fd.append('descripcion', this.anexoForm.descripcion);
fd.append('archivo', fileInput.files[0]);
try {
var resp = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/add-anexo', { method: 'POST', body: fd });
var json = await resp.json();
if (json.success) {
if (window.Notify) Notify.success('Anexo agregado exitosamente.');
this.anexoForm = { descripcion: '' };
fileInput.value = '';
var resp2 = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/get-anexos?id=' + this.anexoSolicitudId);
var json2 = await resp2.json();
if (json2.success) this.anexos = json2.data || [];
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el anexo.');
}
} catch (e) {
console.error('Error al agregar anexo:', e);
if (window.Notify) Notify.error('Error al agregar el anexo.');
} finally {
this.subiendoAnexo = false;
}
},

async eliminarAnexo(id) {
var res = await this.deleteAction({
url: '/departamento-operativo/corporativo/aclaracion-voucher/delete-anexo',
id: id,
name: 'Anexo #' + id
});
if (res && res.success) {
var resp = await fetch('/departamento-operativo/corporativo/aclaracion-voucher/get-anexos?id=' + this.anexoSolicitudId);
var json = await resp.json();
if (json.success) this.anexos = json.data || [];
}
},

downloadFile(tipo, archivo) {
if (!archivo) {
if (window.Notify) Notify.error('Archivo no disponible');
return;
}
var url = '/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(archivo);
window.open(url, '_blank');
}
}));

});

document.addEventListener('DOMContentLoaded', function () {
avActualizarToolOpciones();
avActualizarPendientes();
});
