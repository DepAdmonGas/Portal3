function biometricosComponent() {
var c = document.getElementById('container');
var anioActual = c ? parseInt(c.dataset.anioActual || (new Date().getFullYear())) : (new Date().getFullYear());

return {
multiestacion: c ? c.dataset.multiestacion === 'true' : false,

registros: [],
catalogoIncidencias: [],
vistaReporte: false,

reporte: {
year: anioActual,
mes: (new Date().getMonth()) + 1,
cargando: false,
html: ''
},

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
guardando: false
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

init() {
var self = this;
window.biometricosComponentInstance = self;
self.$nextTick(function() {
fetch('/departamento-operativo/recursos-humanos/biometricos/get-incidencias-catalogo')
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

var f1 = document.getElementById('bioFileDetalleIncidencia');
if (f1) f1.value = '';
var f2 = document.getElementById('bioFileIncidenciaExistente');
if (f2) f2.value = '';
var f3 = document.getElementById('bioFileIncidenciaNueva');
if (f3) f3.value = '';
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
self.abrirModal('modalDetalleIncidenciaBiometricos');

fetch('/departamento-operativo/recursos-humanos/biometricos/get-incidencia-por-asistencia?id_asistencia=' + idAsistencia)
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
self.abrirModal('modalAgregarIncidenciaBiometricos');

fetch('/departamento-operativo/recursos-humanos/biometricos/get-incidencia-por-asistencia?id_asistencia=' + idAsistencia)
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

var fileInput = document.getElementById('bioFileIncidenciaNueva');
if (fileInput && fileInput.files.length > 0) {
formData.append('documento', fileInput.files[0]);
}

var response = await fetch('/departamento-operativo/recursos-humanos/biometricos/agregar-incidencia', {
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
self.cerrarModal('modalAgregarIncidenciaBiometricos');
if (window.tablaBiometricos) window.tablaBiometricos.ajax.reload(null, false);
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

var fileInput = document.getElementById('bioFileDetalleIncidencia');
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

var response = await fetch('/departamento-operativo/recursos-humanos/biometricos/subir-documento-incidencia', {
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
if (window.tablaBiometricos) window.tablaBiometricos.ajax.reload(null, false);
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

var fileInput = document.getElementById('bioFileIncidenciaExistente');
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

var response = await fetch('/departamento-operativo/recursos-humanos/biometricos/subir-documento-incidencia', {
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
self.cerrarModal('modalAgregarIncidenciaBiometricos');
if (window.tablaBiometricos) window.tablaBiometricos.ajax.reload(null, false);
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

abrirModalReporte() {
var hoy = new Date();
this.reporte.year = hoy.getFullYear();
this.reporte.mes = hoy.getMonth() + 1;
this.abrirModal('modalBuscarReporteBiometricos');
},

async buscarReporte() {
var self = this;

if (!self.reporte.year || !self.reporte.mes) {
Notify.error('Selecciona el año y el mes del reporte.');
return;
}

self.reporte.cargando = true;

try {
var response = await fetch('/departamento-operativo/recursos-humanos/biometricos/get-reporte?year=' + self.reporte.year + '&mes=' + self.reporte.mes, {
headers: { 'Accept': 'application/json' }
});

var resp = await response.json();
self.reporte.cargando = false;

if (resp.success) {
self.reporte.html = resp.html || '';
self.vistaReporte = true;
self.cerrarModal('modalBuscarReporteBiometricos');
} else {
Notify.error(resp.message || 'No se pudo generar el reporte.');
}
} catch (err) {
self.reporte.cargando = false;
Notify.error('Error de conexión.');
}
},

nombreMesReporte() {
var nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
return nombres[this.reporte.mes] || '';
},

regresarListado() {
this.vistaReporte = false;
this.reporte.html = '';
if (window.tablaBiometricos) {
window.tablaBiometricos.ajax.reload(null, false);
}
var lista = document.getElementById('divListadoAsistencia');
if (lista) lista.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
};
}
