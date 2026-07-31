document.addEventListener('DOMContentLoaded', function () {

var messageEl = document.getElementById('seguros-empty-message');
var contentEl = document.getElementById('seguros-content');

var table = null;
var permisos = {};

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

function esModoGlobal() {
var sel = document.getElementById('module-station-selector-seguros');
if (sel) return !sel.value;
var container = document.getElementById('container');
return container ? container.dataset.multiestacion === 'true' && !(parseInt(container.dataset.idEstacion || '0') > 0) : false;
}

function sgActualizarToolOpciones() {
var global = esModoGlobal();
var acciones = document.getElementById('seguros-acciones');
if (acciones) acciones.style.display = global ? 'none' : '';
if (table) {
table.column(1).visible(global);
}
}

function initTable() {
var dt = $('#tabla-seguros').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/departamento-operativo/seguros/data',
type: 'GET',
dataSrc: function (json) {
if (!json.success) return [];
permisos = json.permisos || {};
window.__segurosPermisos = permisos;
var container = document.querySelector('#container');
if (container && window.Alpine) {
Alpine.evaluate(container, 'permisos = window.__segurosPermisos || {}');
}
return json.data || [];
}
},
columns: [
{ title:'#', data: 'num', className: 'text-center fw-bold', width: '40px' },
{ title:'Estación / departamento', data: 'localidad_nombre', className: 'text-center', visible: esModoGlobal() },
{ title:'Fecha', data: 'fecha', className: 'text-center' },
{ title:'Hora', data: 'hora', className: 'text-center' },
{ title:'Asunto', data: 'asunto', className: 'text-center' },
{ title:'Observaciones', data: 'observaciones', className: 'text-start' },
{ title:'Solución', data: 'solucion', className: 'text-start' },
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
className: 'text-center',
width: '50px',
orderable: false,
searchable: false,
render: function (d) {
var archivo = d.archivo || '';
var html = '<div x-data="{}">'
+ '<div class="dropdown dropstart">'
+ '<a href="javascript:void(0)" data-bs-toggle="dropdown">'
+ '<i class="ti ti-dots-vertical fs-5 text-muted"></i>'
+ '</a>'
+ '<div class="dropdown-menu pointer">'
+ '<a class="dropdown-item" @click="$dispatch(\'seguros:detalle\', { id: ' + d.id + ' })">'
+ '<i class="ti ti-eye me-1"></i> Detalle</a>';
if (archivo) {
var escArchivo = (archivo || '').replace(/'/g, "\\'");
html += '<a class="dropdown-item" @click="download(\'seguros-incidencias\', \'' + escArchivo + '\')">'
+ '<i class="ti ti-download me-1"></i> Descargar archivo</a>';
} else {
html += '<a class="dropdown-item grayscale">'
+ '<i class="ti ti-download me-1"></i> Descargar archivo</a>';
}
html += '<a class="dropdown-item" @click="$dispatch(\'seguros:editar\', { id: ' + d.id + ' })">'
+ '<i class="ti ti-pencil me-1"></i> Editar</a>'
+ '<a class="dropdown-item" @click="$dispatch(\'seguros:eliminar\', { id: ' + d.id + ' })">'
+ '<i class="ti ti-trash me-1"></i> Eliminar</a>'
+ '</div></div></div>';
return html;
}
}
],
columnDefs: [
{ targets: '_all', render: function (d) { return d || ''; } }
],
drawCallback: function () {
if (window.Alpine) Alpine.initTree(document.querySelector('#tabla-seguros'));
}
});

dt.on('xhr', function (e, settings, json) {
if (!json || !json.success) return;
window.__segurosGlobal = !!json.global;
sgActualizarToolOpciones();
});

return dt;
}

function getOrCreateTable() {
if (!table) {
if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tabla-seguros')) {
table = $('#tabla-seguros').DataTable();
} else {
table = initTable();
}
}
return table;
}

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showTable();
getOrCreateTable();
}

sgActualizarToolOpciones();

var selSeguros = document.getElementById('module-station-selector-seguros');
if (selSeguros) {
selSeguros.addEventListener('change', sgActualizarToolOpciones);
}

ModuleStationSelector.init('seguros', {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null && !esModoGlobal()) {
ms.hideBadge();
showEmptyMessage();
return;
}
showTable();
if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tabla-seguros')) {
$('#tabla-seguros').DataTable().ajax.reload(null, false);
} else {
table = initTable();
}
sgActualizarToolOpciones();
}
});

});

document.addEventListener('alpine:init', function () {
Alpine.data('segurosComponent', function () {
return {
detalle: {},
incidenciaModalTitle: '',
incidenciaForm: {
fecha: '',
hora: '',
asunto: '',
observaciones: '',
solucion: '',
},
incidenciaModo: 'agregar',
incidenciaEditId: 0,
polizaForm: {
emision: '',
vencimiento: '',
},
polizaModo: 'agregar',
polizaEditId: 0,
polizas: [],
permisos: window.__segurosPermisos || {},

abrirDetalle: function (id) {
var self = this;
axios.get('/departamento-operativo/seguros/detalle-incidencia', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
self.detalle = res.data.data;
var modal = new bootstrap.Modal(document.getElementById('modal-detalle'));
modal.show();
}
}).catch(function () {
self.notify('error', 'Error al obtener detalle');
});
},

abrirModalAgregar: function () {
this.incidenciaModalTitle = 'Agregar (en caso de incidencias)';
this.incidenciaModo = 'agregar';
this.incidenciaEditId = 0;
this.incidenciaForm = { fecha: '', hora: '', asunto: '', observaciones: '', solucion: '' };
var modal = new bootstrap.Modal(document.getElementById('modal-incidencia'));
modal.show();
},

resetModalIncidencia: function () {
this.incidenciaModo = 'agregar';
this.incidenciaEditId = 0;
},

abrirModalEditar: function (id) {
this.incidenciaEditId = id;
this.incidenciaModo = 'editar';
this.incidenciaModalTitle = 'Editar (en caso de incidencias)';
var self = this;
axios.get('/departamento-operativo/seguros/detalle-incidencia', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
var d = res.data.data;
self.incidenciaForm = {
fecha: d.fecha_raw || '',
hora: d.hora_raw || '',
asunto: d.asunto || '',
observaciones: d.observaciones || '',
solucion: d.solucion || '',
};
var modal = new bootstrap.Modal(document.getElementById('modal-incidencia'));
modal.show();
}
}).catch(function () {
self.notify('error', 'Error al obtener datos');
});
},

        cancelarEdicionIncidencia: function () {
            var modal = bootstrap.Modal.getInstance(document.getElementById('modal-incidencia'));
            if (modal) modal.hide();
        },

guardarIncidencia: function () {
if (!this.incidenciaForm.fecha || !this.incidenciaForm.hora || !this.incidenciaForm.asunto || !this.incidenciaForm.observaciones || !this.incidenciaForm.solucion) {
this.notify('error', 'Todos los campos son requeridos');
return;
}

if (this.incidenciaModo === 'agregar') {
var fileInput = this.$refs.incidenciaFileInput;
if (!fileInput || !fileInput.files || !fileInput.files[0]) {
this.notify('error', 'Archivo de evidencia requerido');
return;
}
}

this.loading = true;
var self = this;
var fd = new FormData();
fd.append('fecha', this.incidenciaForm.fecha);
fd.append('hora', this.incidenciaForm.hora);
fd.append('asunto', this.incidenciaForm.asunto);
fd.append('observaciones', this.incidenciaForm.observaciones);
fd.append('solucion', this.incidenciaForm.solucion);

if (this.incidenciaModo === 'editar') {
fd.append('id', this.incidenciaEditId);
}

var fileInput = this.$refs.incidenciaFileInput;
if (fileInput && fileInput.files && fileInput.files[0]) {
fd.append('Evidencia_file', fileInput.files[0]);
}

var url = this.incidenciaModo === 'editar'
? '/departamento-operativo/seguros/editar-incidencia'
: '/departamento-operativo/seguros/guardar-incidencia';

axios.post(url, fd, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(function (res) {
if (res.data.success) {
self.notify('success', res.data.message);
var modal = bootstrap.Modal.getInstance(document.getElementById('modal-incidencia'));
if (modal) modal.hide();
self.incidenciaForm = { fecha: '', hora: '', asunto: '', observaciones: '', solucion: '' };
reloadTable();
} else {
self.notify('error', res.data.message || 'Error al guardar');
}
}).catch(function () {
self.notify('error', 'Error de conexión');
}).then(function () {
self.loading = false;
});
},

async eliminarIncidencia(id) {
var res = await this.deleteAction({
url: '/departamento-operativo/seguros/eliminar-incidencia',
id: id,
name: 'Incidencia #' + id,
table: null
});
if (res && res.success) {
reloadTable();
}
},

abrirModalPoliza: function () {
this.polizaModo = 'agregar';
this.polizaEditId = 0;
this.polizaForm = { emision: '', vencimiento: '' };
this.cargarPolizas();
var fileInput = this.$refs.polizaFileInput;
if (fileInput) fileInput.value = '';
var modal = new bootstrap.Modal(document.getElementById('modal-poliza'));
modal.show();
},

cargarPolizas: function () {
var self = this;
axios.get('/departamento-operativo/seguros/polizas').then(function (res) {
if (res.data.success) {
self.polizas = res.data.data || [];
}
}).catch(function () {});
},

calcularVencimiento: function () {
if (!this.polizaForm.emision) return;
var self = this;
axios.get('/departamento-operativo/seguros/vencimiento', {
params: { emision: this.polizaForm.emision }
}).then(function (res) {
if (res.data.success) {
self.polizaForm.vencimiento = res.data.vencimiento;
}
}).catch(function () {});
},

guardarPoliza: function () {
if (!this.polizaForm.emision || !this.polizaForm.vencimiento) {
this.notify('error', 'Fechas de emisión y vencimiento requeridas');
return;
}
if (this.polizaModo === 'agregar') {
var fileInput = this.$refs.polizaFileInput;
if (!fileInput || !fileInput.files || !fileInput.files[0]) {
this.notify('error', 'Archivo PDF requerido');
return;
}
}
this.loading = true;
var self = this;
var fd = new FormData();
fd.append('id', this.polizaEditId);
fd.append('emision', this.polizaForm.emision);
fd.append('vencimiento', this.polizaForm.vencimiento);

var fileInput = this.$refs.polizaFileInput;
if (fileInput && fileInput.files && fileInput.files[0]) {
fd.append('Poliza_file', fileInput.files[0]);
}

var url = this.polizaModo === 'editar'
? '/departamento-operativo/seguros/editar-poliza'
: '/departamento-operativo/seguros/guardar-poliza';

axios.post(url, fd, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(function (res) {
if (res.data.success) {
self.notify('success', res.data.message);
self.cargarPolizas();
self.polizaForm = { emision: '', vencimiento: '' };
self.polizaModo = 'agregar';
self.polizaEditId = 0;
if (fileInput) fileInput.value = '';
} else {
self.notify('error', res.data.message || 'Error al guardar');
}
}).catch(function () {
self.notify('error', 'Error de conexión');
}).then(function () {
self.loading = false;
});
},

editarPoliza: function (id) {
var self = this;
axios.get('/departamento-operativo/seguros/detalle-poliza', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
var d = res.data.data;
self.polizaModo = 'editar';
self.polizaEditId = d.id;
self.polizaForm = {
emision: d.emision || '',
vencimiento: d.vencimiento || '',
};
var fileInput = self.$refs.polizaFileInput;
if (fileInput) fileInput.value = '';
}
}).catch(function () {
self.notify('error', 'Error al obtener datos');
});
},

cancelarEdicionPoliza: function () {
this.polizaModo = 'agregar';
this.polizaEditId = 0;
this.polizaForm = { emision: '', vencimiento: '' };
var fileInput = this.$refs.polizaFileInput;
if (fileInput) fileInput.value = '';
},

resetModalPoliza: function () {
this.polizaModo = 'agregar';
this.polizaEditId = 0;
},

async eliminarPoliza(id) {
var res = await this.deleteAction({
url: '/departamento-operativo/seguros/eliminar-poliza',
id: id,
name: 'Póliza #' + id,
table: null
});
if (res && res.success) {
this.cargarPolizas();
}
},
};
});
});

function reloadTable() {
var t = $('#tabla-seguros');
if ($.fn.DataTable && $.fn.DataTable.isDataTable(t)) {
t.DataTable().ajax.reload(null, false);
}
}