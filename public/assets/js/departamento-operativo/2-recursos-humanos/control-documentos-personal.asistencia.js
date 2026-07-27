function asistenciaComponent() {
var c = document.getElementById('asistencia-container');
return {
idPersonal: c ? parseInt(c.dataset.idPersonal || '0') : 0,
mesActual: c ? parseInt(c.dataset.mesActual || '0') : 0,
anioActual: c ? parseInt(c.dataset.anioActual || '0') : 0,
registros: [],
catalogoIncidencias: [],

modalIncidencia: {
cargando: false,
existe: false,
idAsistencia: 0,
fecha: '',
incidencia: '',
comentario: '',
documento: '',
requiereDocumento: false,
puntos: 0,
fechaInicio: '',
fechaFin: '',
sueldoDia: '',
idIncidenciaSeleccionada: null,
errorRadio: false,
errorComentario: false,
guardando: false,
},

get requiereDocumento() {
var sel = this.modalIncidencia.idIncidenciaSeleccionada;
if (!sel) return false;
for (var i = 0; i < this.catalogoIncidencias.length; i++) {
if (this.catalogoIncidencias[i].id === parseInt(sel)) {
return parseInt(this.catalogoIncidencias[i].documento) === 1;
}
}
return false;
},

get existeRequiereDocumento() {
return !!this.modalIncidencia.requiereDocumento;
},

init() {
var self = this;
self.$nextTick(function() {
self.initDataTable();
fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-incidencias-catalogo')
.then(function(r) { return r.json(); })
.then(function(json) {
if (json.success) self.catalogoIncidencias = json.data || [];
})
.catch(function() {});
});
},

resetModal() {
this.modalIncidencia.cargando = false;
this.modalIncidencia.existe = false;
this.modalIncidencia.idAsistencia = 0;
this.modalIncidencia.fecha = '';
this.modalIncidencia.incidencia = '';
this.modalIncidencia.comentario = '';
this.modalIncidencia.documento = '';
this.modalIncidencia.requiereDocumento = false;
this.modalIncidencia.puntos = 0;
this.modalIncidencia.fechaInicio = '';
this.modalIncidencia.fechaFin = '';
this.modalIncidencia.sueldoDia = '';
this.modalIncidencia.idIncidenciaSeleccionada = null;
this.modalIncidencia.errorRadio = false;
this.modalIncidencia.errorComentario = false;
this.modalIncidencia.guardando = false;

var fileInput = document.getElementById('cdFileDetalleIncidencia');
if (fileInput) fileInput.value = '';
var fileInput2 = document.getElementById('cdFileIncidenciaExistente');
if (fileInput2) fileInput2.value = '';
var fileInput3 = document.getElementById('cdFileIncidenciaNueva');
if (fileInput3) fileInput3.value = '';
},

initDataTable() {
var self = this;
var tableEl = document.getElementById('tabla-asistencia');
if (!tableEl) return;

if (window.tablaAsistencia) {
try { window.tablaAsistencia.destroy(); } catch (e) {}
window.tablaAsistencia = null;
}

if (!tableEl.parentNode) return;

var $table = $(tableEl);

var columns = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '40px' },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center text-nowrap' },
{ title: 'Sistema (Entrada)', data: 'hora_entrada', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sistema (Salida)', data: 'hora_salida', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sensor (Entrada)', data: 'hora_entrada_sensor', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sensor (Salida)', data: 'hora_salida_sensor', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Detalle', data: null, className: 'align-middle text-center',
orderable: false, searchable: false,
render: function(v, t, row) {
var detalle = row.detalle || '';
if (!detalle) return '<span class="text-muted">S/I</span>';
var cls = row.detalle_badge || 'bg-secondary';
return '<span class="badge ' + cls + ' fw-semibold">' + detalle + '</span>';
}
},
{ title: 'Incidencia', data: null, className: 'align-middle text-center', width: '98px' ,
orderable: false, searchable: false,
render: function(v, t, row) {
var fechaRaw = row.fecha_raw || '';
var incidenciaDias = parseInt(row.incidencia_dias || 0);
var deadline = '';
if (fechaRaw && fechaRaw.length >= 10) {
var d = new Date(fechaRaw.substring(0, 10));
d.setDate(d.getDate() + incidenciaDias);
deadline = d.toISOString().substring(0, 10);
}
var hoy = new Date();
var hoyStr = hoy.getFullYear() + '-' +
String(hoy.getMonth() + 1).padStart(2, '0') + '-' +
String(hoy.getDate()).padStart(2, '0');

var tieneIncidencia = parseInt(row.total_incidencias || 0) > 0;

if (deadline && deadline < hoyStr) {
var cls = tieneIncidencia ? 'ti ti-alert-triangle text-warning' : 'ti ti-eye text-primary';
return '<i class=" fs-7 pointer ' + cls + ' cd-btn-detalle-incidencia" data-id="' + row.id + '"></i>';
}

var cls2 = tieneIncidencia ? 'ti ti-alert-triangle text-warning' : 'ti ti-alert-triangle text-primary';
return '<i class="fs-7 pointer ' + cls2 + ' cd-btn-agregar-incidencia" data-id="' + row.id + '"></i>';
}
}
];

window.tablaAsistencia = $table.DataTable({
processing: false,
serverSide: false,
deferRender: true,
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 25,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
ajax: {
url: '/departamento-operativo/recursos-humanos/control-documentos-personal/get-asistencia-data',
data: { id_personal: self.idPersonal },
dataSrc: function(json) {
if (json.success && json.data) {
self.registros = json.data;
return json.data;
}
return [];
}
},
columns: columns
});

$table.off('click', '.cd-btn-detalle-incidencia');
$table.on('click', '.cd-btn-detalle-incidencia', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
self.verDetalleIncidencia(id);
});

$table.off('click', '.cd-btn-agregar-incidencia');
$table.on('click', '.cd-btn-agregar-incidencia', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
self.agregarIncidenciaModal(id);
});
},

findRecord(id) {
for (var i = 0; i < this.registros.length; i++) {
if (parseInt(this.registros[i].id) === id) {
return this.registros[i];
}
}
return null;
},

abrirModal(modalId) {
var el = document.getElementById(modalId);
if (el) {
bootstrap.Modal.getOrCreateInstance(el).show();
}
},

cerrarModal(modalId) {
var el = document.getElementById(modalId);
if (el) {
var inst = bootstrap.Modal.getInstance(el);
if (inst) inst.hide();
}
setTimeout(function() { document.activeElement.blur(); }, 100);
},

verDetalleIncidencia(idAsistencia) {
var self = this;
var record = this.findRecord(idAsistencia);
if (!record) {
Notify.error('No se encontró el registro.');
return;
}

self.resetModal();
self.modalIncidencia.idAsistencia = idAsistencia;
self.modalIncidencia.fecha = record.fecha;
self.modalIncidencia.cargando = true;
self.abrirModal('modalDetalleIncidencia');

fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-incidencia-por-asistencia?id_asistencia=' + idAsistencia)
.then(function(r) { return r.json(); })
.then(function(json) {
self.modalIncidencia.cargando = false;
if (json.success && json.data) {
self.modalIncidencia.existe = json.data.existe || false;
self.modalIncidencia.fecha = json.data.fecha;
self.modalIncidencia.incidencia = json.data.incidencia || '';
self.modalIncidencia.comentario = json.data.comentario || '';
self.modalIncidencia.documento = json.data.documento || '';
self.modalIncidencia.requiereDocumento = json.data.requiere_documento || false;
self.modalIncidencia.puntos = json.data.puntos || 0;
self.modalIncidencia.fechaInicio = json.data.fecha_inicio_raw || '';
self.modalIncidencia.fechaFin = json.data.fecha_fin_raw || '';
self.modalIncidencia.sueldoDia = json.data.sueldo_dia !== undefined && json.data.sueldo_dia !== null ? json.data.sueldo_dia : '';
}
})
.catch(function() {
self.modalIncidencia.cargando = false;
});
},

agregarIncidenciaModal(idAsistencia) {
var self = this;
var record = this.findRecord(idAsistencia);
if (!record) {
Notify.error('No se encontró el registro.');
return;
}

self.resetModal();
self.modalIncidencia.idAsistencia = idAsistencia;
self.modalIncidencia.fecha = record.fecha;
self.modalIncidencia.cargando = true;
self.abrirModal('modalAgregarIncidencia');

fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-incidencia-por-asistencia?id_asistencia=' + idAsistencia)
.then(function(r) { return r.json(); })
.then(function(json) {
self.modalIncidencia.cargando = false;
if (json.success && json.data && json.data.existe) {
self.modalIncidencia.existe = true;
self.modalIncidencia.fecha = json.data.fecha;
self.modalIncidencia.incidencia = json.data.incidencia || '';
self.modalIncidencia.comentario = json.data.comentario || '';
self.modalIncidencia.documento = json.data.documento || '';
self.modalIncidencia.requiereDocumento = json.data.requiere_documento || false;
self.modalIncidencia.puntos = json.data.puntos || 0;
self.modalIncidencia.fechaInicio = json.data.fecha_inicio_raw || '';
self.modalIncidencia.fechaFin = json.data.fecha_fin_raw || '';
self.modalIncidencia.sueldoDia = json.data.sueldo_dia !== undefined && json.data.sueldo_dia !== null ? json.data.sueldo_dia : '';
} else {
self.modalIncidencia.existe = false;
self.modalIncidencia.fecha = record.fecha;
}
})
.catch(function() {
self.modalIncidencia.cargando = false;
});
},

async guardarIncidencia() {
var self = this;
var m = this.modalIncidencia;

m.errorRadio = !m.idIncidenciaSeleccionada;
m.errorComentario = !m.comentario || !m.comentario.trim();

if (m.errorRadio) {
Notify.error('Selecciona un tipo de incidencia.');
return;
}
if (m.errorComentario) {
Notify.error('Escribe un comentario.');
return;
}

var result = await Swal.fire({
title: 'Agregar Incidencia',
text: '¿Desea agregar la incidencia?',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, agregar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});

if (!result.isConfirmed) return;

m.guardando = true;

try {
var formData = new FormData();
formData.append('id_asistencia', m.idAsistencia);
formData.append('id_incidencia', m.idIncidenciaSeleccionada);
formData.append('comentario', m.comentario.trim());

if (m.fechaInicio) formData.append('fecha_inicio', m.fechaInicio);
if (m.fechaFin) formData.append('fecha_fin', m.fechaFin);
if (m.sueldoDia !== '' && m.sueldoDia !== undefined) formData.append('sueldo_dia', m.sueldoDia);

var fileInput = document.getElementById('cdFileIncidenciaNueva');
if (fileInput && fileInput.files.length > 0) {
formData.append('documento', fileInput.files[0]);
}

var response = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/agregar-incidencia', {
method: 'POST',
body: formData
});

var resp = await response.json();
m.guardando = false;

if (resp.success) {
Swal.fire({
icon: 'success',
title: 'Correcto',
text: resp.message || 'Incidencia registrada correctamente.',
timer: 2000,
showConfirmButton: false
});
self.cerrarModal('modalAgregarIncidencia');
window.tablaAsistencia.ajax.reload(null, false);
} else {
Swal.fire({
icon: 'warning',
title: 'Atención',
text: resp.message || 'No se pudo registrar la incidencia.',
timer: 2500,
showConfirmButton: false
});
}
} catch (err) {
m.guardando = false;
Swal.fire({
icon: 'error',
title: 'Error',
text: 'Error de conexión.',
timer: 2000,
showConfirmButton: false
});
}
},

async guardarDocumentoIncidencia() {
var self = this;
var m = this.modalIncidencia;

var fileInput = document.getElementById('cdFileDetalleIncidencia');
if (!fileInput || !fileInput.files.length) {
Notify.error('Selecciona un archivo PDF.');
return;
}

var file = fileInput.files[0];
if (file.type !== 'application/pdf') {
Notify.error('El archivo debe ser formato PDF.');
return;
}

var result = await Swal.fire({
title: 'Guardar documento',
text: '¿Desea guardar el documento de incapacidad?',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, guardar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});

if (!result.isConfirmed) return;

m.guardando = true;

try {
var formData = new FormData();
formData.append('id_asistencia', m.idAsistencia);
formData.append('documento', file);

var response = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/subir-documento-incidencia', {
method: 'POST',
body: formData
});

var resp = await response.json();
m.guardando = false;

if (resp.success) {
Swal.fire({
icon: 'success',
title: 'Correcto',
text: resp.message || 'Documento guardado correctamente.',
timer: 2000,
showConfirmButton: false
});
self.verDetalleIncidencia(m.idAsistencia);
window.tablaAsistencia.ajax.reload(null, false);
} else {
Swal.fire({
icon: 'warning',
title: 'Atención',
text: resp.message || 'No se pudo guardar el documento.',
timer: 2500,
showConfirmButton: false
});
}
} catch (err) {
m.guardando = false;
Swal.fire({
icon: 'error',
title: 'Error',
text: 'Error de conexión.',
timer: 2000,
showConfirmButton: false
});
}
},

async guardarDocumentoIncidenciaExistente() {
var self = this;
var m = this.modalIncidencia;

var fileInput = document.getElementById('cdFileIncidenciaExistente');
if (!fileInput || !fileInput.files.length) {
Notify.error('Selecciona un archivo PDF.');
return;
}

var file = fileInput.files[0];
if (file.type !== 'application/pdf') {
Notify.error('El archivo debe ser formato PDF.');
return;
}

if (!m.fechaInicio) {
Notify.error('Ingresa la fecha de inicio de incapacidad.');
return;
}
if (!m.fechaFin) {
Notify.error('Ingresa la fecha fin de incapacidad.');
return;
}
if (m.fechaInicio > m.fechaFin) {
Notify.error('La fecha fin debe ser mayor o igual a la fecha de inicio.');
return;
}

var result = await Swal.fire({
title: 'Guardar documento',
text: '¿Desea guardar la documentación de incapacidad?',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, guardar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});

if (!result.isConfirmed) return;

m.guardando = true;

try {
var formData = new FormData();
formData.append('id_asistencia', m.idAsistencia);
formData.append('documento', file);
formData.append('fecha_inicio', m.fechaInicio);
formData.append('fecha_fin', m.fechaFin);
if (m.sueldoDia !== '' && m.sueldoDia !== undefined) {
formData.append('sueldo_dia', m.sueldoDia);
}

var response = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/subir-documento-incidencia', {
method: 'POST',
body: formData
});

var resp = await response.json();
m.guardando = false;

if (resp.success) {
Swal.fire({
icon: 'success',
title: 'Correcto',
text: resp.message || 'Documento guardado correctamente.',
timer: 2000,
showConfirmButton: false
});
self.cerrarModal('modalAgregarIncidencia');
window.tablaAsistencia.ajax.reload(null, false);
} else {
Swal.fire({
icon: 'warning',
title: 'Atención',
text: resp.message || 'No se pudo guardar el documento.',
timer: 2500,
showConfirmButton: false
});
}
} catch (err) {
m.guardando = false;
Swal.fire({
icon: 'error',
title: 'Error',
text: 'Error de conexión.',
timer: 2000,
showConfirmButton: false
});
}
}
};
}
